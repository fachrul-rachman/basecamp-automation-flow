<?php

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\Notion\Contracts\NotionClient;
use App\Core\Shared\Notion\Data\NotionCreatePageRequest;
use App\Core\Shared\Notion\Data\NotionCreatePageResponse;
use App\Core\Shared\Notion\Exceptions\NotionDeliveryException;
use App\Core\Shared\Notion\Services\HttpNotionClient;
use App\Core\Shared\Support\Contracts\Sleeper;
use App\Modules\KpusGaHw\Application\Services\BuildKpusGaHwNotionPageRequest;
use App\Modules\KpusGaHw\Application\Services\PublishAuditResultToNotion;
use App\Modules\KpusGaHw\Application\Services\PublishPendingAuditResultsToNotion;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.notion.data_source_id' => '388901df-9413-8000-b842-000b948b6f20',
        'services.notion.version' => '2025-09-03',
        'services.notion.token' => 'secret_test',
    ]);
});

it('builds the verified Notion create-page payload with data source parent and exact property types', function (): void {
    $audit = stageFiveAudit(status: AuditStatus::Baik, reason: 'Sesuai ketentuan');
    $request = app(BuildKpusGaHwNotionPageRequest::class)->handle($audit);

    expect($request->toPayload())->toBe([
        'parent' => [
            'type' => 'data_source_id',
            'data_source_id' => '388901df-9413-8000-b842-000b948b6f20',
        ],
        'properties' => [
            'Area' => [
                'title' => [
                    [
                        'text' => [
                            'content' => 'Pantry',
                        ],
                    ],
                ],
            ],
            'Tanggal' => [
                'date' => [
                    'start' => '2026-06-23',
                ],
            ],
            'Alasan' => [
                'rich_text' => [
                    [
                        'text' => [
                            'content' => 'Sesuai ketentuan',
                        ],
                    ],
                ],
            ],
            'Bukti' => [
                'url' => 'https://app.basecamp.com/4888518/buckets/47333489/todos/10018362592',
            ],
            'Status' => [
                'status' => [
                    'name' => 'Baik',
                ],
            ],
        ],
    ]);
});

it('maps all final statuses exactly for Notion status property', function (AuditStatus $status): void {
    $request = app(BuildKpusGaHwNotionPageRequest::class)->handle(stageFiveAudit(status: $status));

    expect($request->toPayload()['properties']['Status']['status']['name'])->toBe($status->value);
})->with([
    'baik' => [AuditStatus::Baik],
    'bermasalah' => [AuditStatus::Bermasalah],
    'need review' => [AuditStatus::NeedReview],
]);

it('stores Notion page ID and delivered status after successful delivery', function (): void {
    $audit = stageFiveAudit(status: AuditStatus::NeedReview);
    $fake = fakeStageFiveNotion([
        new NotionCreatePageResponse(pageId: 'notion-page-1', url: 'https://notion.so/page'),
    ]);
    fakeStageFiveSleeper();

    $result = app(PublishAuditResultToNotion::class)->handle($audit);

    expect($fake->requests)->toHaveCount(1)
        ->and($result->notion_delivery_status)->toBe(NotionDeliveryStatus::Delivered)
        ->and($result->notion_page_id)->toBe('notion-page-1')
        ->and($result->notion_delivered_at)->not->toBeNull()
        ->and($result->notion_attempts)->toBe(1)
        ->and($result->last_notion_error)->toBeNull();
});

it('retries failed Notion writes three times with five second delays and preserves business status', function (): void {
    $audit = stageFiveAudit(status: AuditStatus::Bermasalah, reason: 'Upload pertama melewati 09:00 WIB');
    fakeStageFiveNotion([
        NotionDeliveryException::failed('Notion request gagal (500)'),
        NotionDeliveryException::failed('Notion request gagal (500)'),
        NotionDeliveryException::failed('Notion request gagal (500)'),
    ]);
    $sleeper = fakeStageFiveSleeper();

    $result = app(PublishAuditResultToNotion::class)->handle($audit);

    expect($result->notion_delivery_status)->toBe(NotionDeliveryStatus::Failed)
        ->and($result->notion_attempts)->toBe(3)
        ->and($result->last_notion_error)->toBe('Notion request gagal (500)')
        ->and($result->status)->toBe(AuditStatus::Bermasalah)
        ->and($result->reason)->toBe('Upload pertama melewati 09:00 WIB')
        ->and($sleeper->seconds)->toBe([5, 5]);
});

it('does not create duplicate Notion rows when page ID already exists', function (): void {
    $audit = stageFiveAudit(status: AuditStatus::Baik)->forceFill([
        'notion_delivery_status' => NotionDeliveryStatus::Delivered,
        'notion_page_id' => 'existing-page',
    ]);
    $audit->save();
    $fake = fakeStageFiveNotion([
        new NotionCreatePageResponse(pageId: 'new-page'),
    ]);
    fakeStageFiveSleeper();

    $result = app(PublishAuditResultToNotion::class)->handle($audit);

    expect($fake->requests)->toHaveCount(0)
        ->and($result->notion_page_id)->toBe('existing-page');
});

it('publishes pending and failed rows through the retry service without touching delivered rows', function (): void {
    $pending = stageFiveAudit(status: AuditStatus::Baik);
    $failed = stageFiveAudit(status: AuditStatus::NeedReview, areaIdentity: '10018362593')->forceFill([
        'notion_delivery_status' => NotionDeliveryStatus::Failed,
        'last_notion_error' => 'previous failure',
    ]);
    $failed->save();
    stageFiveAudit(status: AuditStatus::Bermasalah, areaIdentity: '10018362594')->forceFill([
        'notion_delivery_status' => NotionDeliveryStatus::Delivered,
        'notion_page_id' => 'already-delivered',
    ])->save();

    fakeStageFiveNotion([
        new NotionCreatePageResponse(pageId: 'page-pending'),
        new NotionCreatePageResponse(pageId: 'page-failed'),
    ]);
    fakeStageFiveSleeper();

    $summary = app(PublishPendingAuditResultsToNotion::class)->handle('2026-06-23');

    expect($summary)->toBe(['checked' => 2, 'delivered' => 2, 'failed' => 0])
        ->and($pending->fresh()->notion_page_id)->toBe('page-pending')
        ->and($failed->fresh()->notion_page_id)->toBe('page-failed');
});

it('runs Notion publishing through Artisan command', function (): void {
    stageFiveAudit(status: AuditStatus::Baik);
    fakeStageFiveNotion([
        new NotionCreatePageResponse(pageId: 'page-command'),
    ]);
    fakeStageFiveSleeper();

    $this->artisan('kpus-ga-hw:publish-notion --report-date=2026-06-23')
        ->assertExitCode(0);

    expect(DailyAreaAudit::query()->firstOrFail()->notion_page_id)->toBe('page-command');
});

it('uses verified Notion API headers and endpoint in the shared HTTP client', function (): void {
    Http::fake([
        'https://api.notion.com/v1/pages' => Http::response([
            'object' => 'page',
            'id' => 'page-http',
            'url' => 'https://notion.so/page-http',
        ], 200, ['x-request-id' => 'request-1']),
    ]);

    $response = app(HttpNotionClient::class)->createPage(
        app(BuildKpusGaHwNotionPageRequest::class)->handle(stageFiveAudit(status: AuditStatus::Baik)),
    );

    expect($response->pageId)->toBe('page-http')
        ->and($response->requestId)->toBe('request-1');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.notion.com/v1/pages'
            && $request->hasHeader('Notion-Version', '2025-09-03')
            && $request->data()['parent']['type'] === 'data_source_id';
    });
});

function stageFiveAudit(
    AuditStatus $status,
    string $reason = 'Sesuai ketentuan',
    string $areaIdentity = '10018362592',
): DailyAreaAudit {
    $project = BasecampProject::query()->firstOrCreate(
        [
            'basecamp_account_id' => '4888518',
            'basecamp_project_id' => '47333489',
        ],
        [
            'name' => 'KPUS GA HW',
            'workflow_type' => 'kpus_ga_hw',
            'active' => true,
        ],
    );

    return DailyAreaAudit::factory()->create([
        'project_id' => $project->id,
        'report_date' => '2026-06-23',
        'area_identity' => $areaIdentity,
        'area_name' => 'Pantry',
        'basecamp_todo_id' => $areaIdentity,
        'basecamp_todo_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todos/10018362592',
        'status' => $status,
        'reason' => $reason,
        'notion_delivery_status' => NotionDeliveryStatus::Pending,
        'notion_page_id' => null,
        'notion_attempts' => 0,
        'last_notion_error' => null,
    ]);
}

function fakeStageFiveNotion(array $responses): FakeStageFiveNotionClient
{
    $fake = new FakeStageFiveNotionClient($responses);
    app()->instance(NotionClient::class, $fake);

    return $fake;
}

function fakeStageFiveSleeper(): FakeStageFiveSleeper
{
    $fake = new FakeStageFiveSleeper;
    app()->instance(Sleeper::class, $fake);

    return $fake;
}

class FakeStageFiveNotionClient implements NotionClient
{
    /** @var list<NotionCreatePageRequest> */
    public array $requests = [];

    public function __construct(private array $responses) {}

    public function createPage(NotionCreatePageRequest $request): NotionCreatePageResponse
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}

class FakeStageFiveSleeper implements Sleeper
{
    /** @var list<int> */
    public array $seconds = [];

    public function sleepSeconds(int $seconds): void
    {
        $this->seconds[] = $seconds;
    }
}

<?php

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\Notion\Contracts\NotionClient;
use App\Core\Shared\Notion\Data\NotionCreatePageRequest;
use App\Core\Shared\Notion\Data\NotionCreatePageResponse;
use App\Core\Shared\Notion\Exceptions\NotionDeliveryException;
use App\Core\Shared\OpenAI\Contracts\VisionReviewClient;
use App\Core\Shared\OpenAI\Data\VisionReviewRequest;
use App\Core\Shared\OpenAI\Data\VisionReviewResponse;
use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Models\Holiday;
use App\Core\Shared\Support\Contracts\Sleeper;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('runs the full daily audit command, publishes to Notion, and logs operational summary', function (): void {
    Log::spy();
    fakeStageSixBasecampResponses([
        stageSixTodoPayload(id: 1001, content: 'Pantry'),
    ], [
        1001 => stageSixPassingComments(1001),
    ]);
    fakeStageSixVision([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);
    fakeStageSixNotion([new NotionCreatePageResponse(pageId: 'page-1', requestId: 'request-1')]);
    fakeStageSixSleeper();

    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')
        ->assertExitCode(0);

    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->status)->toBe(AuditStatus::Baik)
        ->and($audit->notion_delivery_status)->toBe(NotionDeliveryStatus::Delivered)
        ->and($audit->notion_page_id)->toBe('page-1');

    Log::shouldHaveReceived('info')->with('kpus_ga_hw.daily_audit.started', Mockery::type('array'))->once();
    Log::shouldHaveReceived('info')->with('kpus_ga_hw.daily_audit.finished', Mockery::type('array'))->once();
});

it('does not duplicate audit rows or Notion pages on daily audit rerun', function (): void {
    fakeStageSixBasecampResponses([
        stageSixTodoPayload(id: 1001, content: 'Pantry'),
    ], [
        1001 => stageSixPassingComments(1001),
    ]);
    $vision = fakeStageSixVision([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);
    $notion = fakeStageSixNotion([new NotionCreatePageResponse(pageId: 'page-1')]);
    fakeStageSixSleeper();

    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')->assertExitCode(0);
    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')->assertExitCode(0);

    expect(DailyAreaAudit::query()->count())->toBe(1)
        ->and($vision->requests)->toHaveCount(1)
        ->and($notion->requests)->toHaveCount(1);
});

it('keeps finalized audit results when Notion delivery fails', function (): void {
    fakeStageSixBasecampResponses([
        stageSixTodoPayload(id: 1001, content: 'Pantry'),
    ], [
        1001 => stageSixPassingComments(1001),
    ]);
    fakeStageSixVision([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);
    fakeStageSixNotion([
        NotionDeliveryException::failed('Notion request gagal (500)'),
        NotionDeliveryException::failed('Notion request gagal (500)'),
        NotionDeliveryException::failed('Notion request gagal (500)'),
    ]);
    fakeStageSixSleeper();

    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')
        ->assertExitCode(0);

    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->status)->toBe(AuditStatus::Baik)
        ->and($audit->notion_delivery_status)->toBe(NotionDeliveryStatus::Failed)
        ->and($audit->notion_attempts)->toBe(3)
        ->and($audit->last_notion_error)->toBe('Notion request gagal (500)');
});

it('does not create false business results when Basecamp is unavailable', function (): void {
    Http::fake([
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(['message' => 'down'], 500),
    ]);
    fakeStageSixVision([]);
    fakeStageSixNotion([]);
    fakeStageSixSleeper();

    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')
        ->assertExitCode(1);

    expect(DailyAreaAudit::query()->count())->toBe(0);
});

it('skips the default daily audit on weekends and configured holidays', function (): void {
    app()->instance(Clock::class, new class implements Clock
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-06-20 09:00:00', 'Asia/Jakarta');
        }
    });

    expect(Artisan::call('kpus-ga-hw:daily-audit'))->toBe(0);

    app()->instance(Clock::class, new class implements Clock
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-06-23 09:00:00', 'Asia/Jakarta');
        }
    });
    Holiday::factory()->create(['holiday_date' => '2026-06-23']);

    expect(Artisan::call('kpus-ga-hw:daily-audit'))->toBe(0);

    expect(DailyAreaAudit::query()->count())->toBe(0);
});

it('publishes a Bermasalah finding when the daily dated to-do list is missing', function (): void {
    fakeStageSixMissingDatedListResponses();
    fakeStageSixVision([]);
    $notion = fakeStageSixNotion([new NotionCreatePageResponse(pageId: 'page-missing-list')]);
    fakeStageSixSleeper();

    $this->artisan('kpus-ga-hw:daily-audit --report-date=2026-06-23')
        ->assertExitCode(0);

    $audit = DailyAreaAudit::query()->firstOrFail();
    $properties = $notion->requests[0]->toPayload()['properties'];

    expect($audit->area_identity)->toBe('missing-dated-list:2026-06-23')
        ->and($audit->area_name)->toBe('Todo List Harian')
        ->and($audit->status)->toBe(AuditStatus::Bermasalah)
        ->and($audit->reason)->toBe('To-do list belum dibuat sebelum jam pengecekan')
        ->and($audit->basecamp_todo_url)->toBe('https://app.basecamp.com/4888518/projects/47333489')
        ->and($audit->notion_delivery_status)->toBe(NotionDeliveryStatus::Delivered)
        ->and($properties['Status']['status']['name'])->toBe('Bermasalah')
        ->and($properties['Validator Status']['status']['name'])->toBe('Uncheck')
        ->and($properties['Bukti']['url'])->toBe('https://app.basecamp.com/4888518/projects/47333489');
});

it('registers the daily scheduler at 09:00 Asia/Jakarta', function (): void {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('kpus-ga-hw:daily-audit')
        ->and($output)->toContain('0 2 * * *');
});

/**
 * @param  list<array<string, mixed>>  $todos
 * @param  array<int, list<array<string, mixed>>>  $commentsByTodo
 */
function fakeStageSixBasecampResponses(array $todos, array $commentsByTodo): void
{
    BasecampProject::query()->updateOrCreate(
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

    $responses = [
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(stageSixProjectPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json' => Http::response(stageSixTodosetPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json' => Http::response(stageSixTodolistPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569/todos.json' => Http::response($todos),
    ];

    foreach ($commentsByTodo as $todoId => $comments) {
        $responses["https://3.basecampapi.com/4888518/buckets/47333489/recordings/{$todoId}/comments.json"] = Http::response($comments);
    }

    Http::fake($responses);
}

function fakeStageSixMissingDatedListResponses(): void
{
    BasecampProject::query()->updateOrCreate(
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

    Http::fake([
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(stageSixProjectPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json' => Http::response(stageSixTodosetPayload([
            [
                'id' => 2000,
                'title' => 'MASTER (JANGAN DIGANTI)',
                'url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todolists/2000.json',
                'app_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todolists/2000',
            ],
        ])),
    ]);
}

/** @return list<array<string, mixed>> */
function stageSixPassingComments(int $todoId): array
{
    return [
        stageSixCommentPayload(id: 1, todoId: $todoId, createdAt: '2026-06-23T01:00:00.000Z', attachments: [
            stageSixAttachmentPayload(id: 'first'),
            stageSixAttachmentPayload(id: 'second'),
        ]),
    ];
}

/** @return array<string, mixed> */
function stageSixProjectPayload(): array
{
    return [
        'id' => 47333489,
        'name' => 'KPUS GA HW',
        'url' => 'https://3.basecampapi.com/4888518/projects/47333489.json',
        'app_url' => 'https://app.basecamp.com/4888518/projects/47333489',
        'dock' => [[
            'id' => 9905161959,
            'title' => 'To-dos',
            'name' => 'todoset',
            'enabled' => true,
            'url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json',
            'app_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todosets/9905161959',
        ]],
    ];
}

/** @return array<string, mixed> */
function stageSixTodosetPayload(?array $todolists = null): array
{
    return [
        'id' => 9905161959,
        'type' => 'Todoset',
        'bucket' => [
            'id' => 47333489,
            'name' => 'KPUS GA HW',
            'type' => 'Project',
        ],
        'todolists' => $todolists ?? [[
            'id' => 10018362569,
            'title' => '23-06-2026',
            'url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json',
            'app_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todolists/10018362569',
        ]],
        'todolists_url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959/todolists.json',
    ];
}

/** @return array<string, mixed> */
function stageSixTodolistPayload(): array
{
    return [
        'id' => 10018362569,
        'title' => '23-06-2026',
        'type' => 'Todolist',
        'url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json',
        'app_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todolists/10018362569',
        'todos_url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569/todos.json',
    ];
}

/** @return array<string, mixed> */
function stageSixTodoPayload(int $id, string $content): array
{
    return [
        'id' => $id,
        'status' => 'active',
        'created_at' => '2026-05-20T01:20:08.737Z',
        'updated_at' => '2026-06-23T01:05:02.362Z',
        'title' => $content,
        'type' => 'Todo',
        'url' => "https://3.basecampapi.com/4888518/buckets/47333489/todos/{$id}.json",
        'app_url' => "https://app.basecamp.com/4888518/buckets/47333489/todos/{$id}",
        'comments_count' => 1,
        'comments_url' => "https://3.basecampapi.com/4888518/buckets/47333489/recordings/{$id}/comments.json",
        'parent' => [
            'id' => 10018362569,
            'title' => '23-06-2026',
            'type' => 'Todolist',
        ],
        'bucket' => [
            'id' => 47333489,
            'name' => 'KPUS GA HW',
            'type' => 'Project',
        ],
        'content' => $content,
        'completed' => false,
    ];
}

/**
 * @param  list<array<string, mixed>>  $attachments
 * @return array<string, mixed>
 */
function stageSixCommentPayload(int $id, int $todoId, string $createdAt, array $attachments): array
{
    return [
        'id' => $id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'type' => 'Comment',
        'parent' => [
            'id' => $todoId,
            'title' => 'Pantry',
            'type' => 'Todo',
        ],
        'content_attachments' => $attachments,
    ];
}

/** @return array<string, mixed> */
function stageSixAttachmentPayload(string $id): array
{
    return [
        'id' => $id,
        'byte_size' => 2428252,
        'content_type' => 'image/png',
        'width' => 1600,
        'height' => 1200,
        'filename' => "{$id}.png",
        'download_url' => "https://download.example.test/{$id}",
        'previewable' => true,
        'preview_url' => "https://preview.example.test/{$id}",
        'thumbnail_url' => "https://thumb.example.test/{$id}",
    ];
}

function fakeStageSixVision(array $responses): FakeStageSixVisionReviewClient
{
    $fake = new FakeStageSixVisionReviewClient($responses);
    app()->instance(VisionReviewClient::class, $fake);

    return $fake;
}

function fakeStageSixNotion(array $responses): FakeStageSixNotionClient
{
    $fake = new FakeStageSixNotionClient($responses);
    app()->instance(NotionClient::class, $fake);

    return $fake;
}

function fakeStageSixSleeper(): FakeStageSixSleeper
{
    $fake = new FakeStageSixSleeper;
    app()->instance(Sleeper::class, $fake);

    return $fake;
}

class FakeStageSixVisionReviewClient implements VisionReviewClient
{
    /** @var list<VisionReviewRequest> */
    public array $requests = [];

    /** @param list<VisionReviewResponse> $responses */
    public function __construct(private array $responses) {}

    public function review(VisionReviewRequest $request): VisionReviewResponse
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? VisionReviewResponse::failure('AI gagal membaca foto');
    }
}

class FakeStageSixNotionClient implements NotionClient
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

class FakeStageSixSleeper implements Sleeper
{
    public function sleepSeconds(int $seconds): void
    {
        //
    }
}

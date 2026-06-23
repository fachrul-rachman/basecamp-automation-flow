<?php

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\OpenAI\Contracts\VisionReviewClient;
use App\Core\Shared\OpenAI\Data\VisionReviewRequest;
use App\Core\Shared\OpenAI\Data\VisionReviewResponse;
use App\Core\Shared\OpenAI\Services\OpenAiVisionReviewClient;
use App\Modules\KpusGaHw\Application\Services\RunAiReviewAudit;
use App\Modules\KpusGaHw\Domain\Enums\AiReviewResult;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('maps AI ok to final Baik and stores concise reasons', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ]);
    fakeVisionClient([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);

    $summary = app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($summary['ai_reviewed'])->toBe(1)
        ->and($summary['baik'])->toBe(1)
        ->and($audit->status)->toBe(AuditStatus::Baik)
        ->and($audit->reason)->toBe('Sesuai ketentuan')
        ->and($audit->ai_result)->toBe(AiReviewResult::Ok)
        ->and($audit->ai_reasons)->toBe(['Tidak terlihat anomali bermakna']);
});

it('maps AI anomaly and uncertain to Need Review without creating Bermasalah', function (string $result): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ]);
    fakeVisionClient([VisionReviewResponse::success([
        'result' => $result,
        'reasons' => ['Perlu dicek manusia'],
        'confidence' => 'low',
    ])]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->status)->toBe(AuditStatus::NeedReview)
        ->and($audit->reason)->toBe('Perlu dicek manusia')
        ->and($audit->status)->not->toBe(AuditStatus::Bermasalah);
})->with([
    'anomaly' => ['anomaly'],
    'uncertain' => ['uncertain'],
]);

it('maps AI API failure to Need Review with a technical reason', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ]);
    fakeVisionClient([VisionReviewResponse::failure('AI gagal membaca foto')]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->status)->toBe(AuditStatus::NeedReview)
        ->and($audit->ai_result)->toBeNull()
        ->and($audit->reason)->toBe('AI gagal membaca foto')
        ->and($audit->ai_reasons)->toBe(['AI gagal membaca foto']);
});

it('does not call AI when objective checks fail', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => [
            stageFourCommentPayload(id: 1, todoId: 1001, createdAt: '2026-06-23T02:05:00.000Z', attachments: [
                stageFourAttachmentPayload(id: 'late-1'),
                stageFourAttachmentPayload(id: 'late-2'),
            ]),
        ],
    ]);
    $fake = fakeVisionClient([]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($fake->requests)->toHaveCount(0)
        ->and($audit->status)->toBe(AuditStatus::Bermasalah);
});

it('sends at most the first two ordered image URLs to AI', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => [
            stageFourCommentPayload(id: 1, todoId: 1001, createdAt: '2026-06-23T01:00:00.000Z', attachments: [
                stageFourAttachmentPayload(id: 'first'),
                stageFourAttachmentPayload(id: 'second'),
            ]),
            stageFourCommentPayload(id: 2, todoId: 1001, createdAt: '2026-06-23T01:05:00.000Z', attachments: [
                stageFourAttachmentPayload(id: 'third'),
            ]),
        ],
    ]);
    $fake = fakeVisionClient([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->imageUrls)->toBe([
            'https://download.example.test/first',
            'https://download.example.test/second',
        ]);
});

it('can prepare Basecamp image downloads as data URLs for OpenAI', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ], includeDownloadResponses: true);
    $fake = fakeVisionClient([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());

    expect($fake->requests[0]->imageUrls[0])->toStartWith('data:image/png;base64,')
        ->and($fake->requests[0]->imageUrls[1])->toStartWith('data:image/png;base64,');
});

it('does not revise an existing finalized result on rerun', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ]);
    $fake = fakeVisionClient([
        VisionReviewResponse::success([
            'result' => 'ok',
            'reasons' => ['Tidak terlihat anomali bermakna'],
            'confidence' => 'medium',
        ]),
        VisionReviewResponse::success([
            'result' => 'anomaly',
            'reasons' => ['Seharusnya tidak dipakai'],
            'confidence' => 'low',
        ]),
    ]);

    app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    app(RunAiReviewAudit::class)->handle(stageFourReportDate());
    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($fake->requests)->toHaveCount(1)
        ->and(DailyAreaAudit::query()->count())->toBe(1)
        ->and($audit->status)->toBe(AuditStatus::Baik);
});

it('rejects invalid OpenAI JSON and retries before returning failure', function (): void {
    config(['services.openai.api_key' => 'test-key', 'services.openai.vision_model' => 'gpt-4.1-mini', 'services.openai.vision_max_attempts' => 2]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::sequence()
            ->push(['output_text' => '{"result":"bad","reasons":[],"confidence":"low"}'])
            ->push(['output_text' => '{"result":"ok","reasons":["Valid"],"confidence":"high"}']),
    ]);

    $response = app(OpenAiVisionReviewClient::class)->review(stageFourVisionRequest());

    expect($response->successful)->toBeTrue()
        ->and($response->json)->toBe([
            'result' => 'ok',
            'reasons' => ['Valid'],
            'confidence' => 'high',
        ]);
});

it('returns AI failure after invalid OpenAI JSON is exhausted', function (): void {
    config(['services.openai.api_key' => 'test-key', 'services.openai.vision_model' => 'gpt-4.1-mini', 'services.openai.vision_max_attempts' => 2]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['output_text' => 'not-json']),
    ]);

    $response = app(OpenAiVisionReviewClient::class)->review(stageFourVisionRequest());

    expect($response->successful)->toBeFalse()
        ->and($response->failureReason)->toBe('AI mengembalikan JSON tidak valid');
});

it('runs AI review through Artisan command', function (): void {
    fakeStageFourBasecampResponses([
        stageFourTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => stageFourPassingComments(1001),
    ]);
    fakeVisionClient([VisionReviewResponse::success([
        'result' => 'ok',
        'reasons' => ['Tidak terlihat anomali bermakna'],
        'confidence' => 'medium',
    ])]);

    $this->artisan('kpus-ga-hw:ai-review --report-date=2026-06-23')
        ->assertExitCode(0);

    expect(DailyAreaAudit::query()->firstOrFail()->status)->toBe(AuditStatus::Baik);
});

function fakeVisionClient(array $responses): FakeStageFourVisionReviewClient
{
    $fake = new FakeStageFourVisionReviewClient($responses);
    app()->instance(VisionReviewClient::class, $fake);

    return $fake;
}

function stageFourReportDate(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-23', 'Asia/Jakarta');
}

function stageFourVisionRequest(): VisionReviewRequest
{
    return new VisionReviewRequest(
        developerPrompt: 'Return JSON.',
        userPrompt: 'Review images.',
        imageUrls: [
            'https://example.test/1.png',
            'https://example.test/2.png',
        ],
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['result', 'reasons', 'confidence'],
            'properties' => [
                'result' => ['type' => 'string', 'enum' => ['ok', 'anomaly', 'uncertain']],
                'reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
            ],
        ],
        schemaName: 'kpus_ga_hw_vision_review',
    );
}

/**
 * @param  list<array<string, mixed>>  $todos
 * @param  array<int, list<array<string, mixed>>>  $commentsByTodo
 */
function fakeStageFourBasecampResponses(array $todos, array $commentsByTodo, bool $includeDownloadResponses = false): void
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
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(stageFourProjectPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json' => Http::response(stageFourTodosetPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json' => Http::response(stageFourTodolistPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569/todos.json' => Http::response($todos),
    ];

    foreach ($commentsByTodo as $todoId => $comments) {
        $responses["https://3.basecampapi.com/4888518/buckets/47333489/recordings/{$todoId}/comments.json"] = Http::response($comments);
    }

    if ($includeDownloadResponses) {
        $responses['https://download.example.test/first'] = Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/png']);
        $responses['https://download.example.test/second'] = Http::response('fake-image-bytes-2', 200, ['Content-Type' => 'image/png']);
    }

    Http::fake($responses);
}

/** @return list<array<string, mixed>> */
function stageFourPassingComments(int $todoId): array
{
    return [
        stageFourCommentPayload(id: 1, todoId: $todoId, createdAt: '2026-06-23T01:00:00.000Z', attachments: [
            stageFourAttachmentPayload(id: 'first'),
            stageFourAttachmentPayload(id: 'second'),
        ]),
    ];
}

/** @return array<string, mixed> */
function stageFourProjectPayload(): array
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
function stageFourTodosetPayload(): array
{
    return [
        'id' => 9905161959,
        'type' => 'Todoset',
        'bucket' => [
            'id' => 47333489,
            'name' => 'KPUS GA HW',
            'type' => 'Project',
        ],
        'todolists' => [[
            'id' => 10018362569,
            'title' => '23-06-2026',
            'url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json',
            'app_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todolists/10018362569',
        ]],
        'todolists_url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959/todolists.json',
    ];
}

/** @return array<string, mixed> */
function stageFourTodolistPayload(): array
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
function stageFourTodoPayload(int $id, string $content): array
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
function stageFourCommentPayload(int $id, int $todoId, string $createdAt, array $attachments): array
{
    return [
        'id' => $id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'type' => 'Comment',
        'parent' => [
            'id' => $todoId,
            'title' => 'BD',
            'type' => 'Todo',
        ],
        'content_attachments' => $attachments,
    ];
}

/** @return array<string, mixed> */
function stageFourAttachmentPayload(string $id, string $contentType = 'image/png'): array
{
    return [
        'id' => $id,
        'byte_size' => 2428252,
        'content_type' => $contentType,
        'width' => $contentType === 'image/png' ? 1600 : null,
        'height' => $contentType === 'image/png' ? 1200 : null,
        'filename' => "{$id}.png",
        'download_url' => "https://download.example.test/{$id}",
        'previewable' => $contentType === 'image/png',
        'preview_url' => "https://preview.example.test/{$id}",
        'thumbnail_url' => "https://thumb.example.test/{$id}",
    ];
}

class FakeStageFourVisionReviewClient implements VisionReviewClient
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

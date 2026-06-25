<?php

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Models\Holiday;
use App\Modules\KpusGaHw\Application\Exceptions\AuditDateSkippedException;
use App\Modules\KpusGaHw\Application\Services\DetermineReportDate;
use App\Modules\KpusGaHw\Application\Services\EvaluateObjectiveRules;
use App\Modules\KpusGaHw\Application\Services\RunObjectiveAudit;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('calculates the current business day and skips weekends or configured holidays', function (): void {
    $clock = new class implements Clock
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-06-23 09:00:00', 'Asia/Jakarta');
        }
    };

    app()->instance(Clock::class, $clock);

    expect(app(DetermineReportDate::class)->handle()->toDateString())->toBe('2026-06-23');

    expect(fn () => app(DetermineReportDate::class)->handle(CarbonImmutable::parse('2026-06-20', 'Asia/Jakarta')))
        ->toThrow(AuditDateSkippedException::class, 'weekend');

    Holiday::factory()->create(['holiday_date' => '2026-06-23']);

    expect(fn () => app(DetermineReportDate::class)->handle(CarbonImmutable::parse('2026-06-23', 'Asia/Jakarta')))
        ->toThrow(AuditDateSkippedException::class, 'configured holiday');
});

it('passes objective check for two images uploaded before or exactly at 09:00 and before 06:00', function (string $uploadedAt): void {
    $result = app(EvaluateObjectiveRules::class)->handle(stageThreeArea(firstUploadAt: $uploadedAt, imageCount: 2), stageThreeReportDate());

    expect($result->passed)->toBeTrue();
})->with([
    'before deadline' => ['2026-06-23T01:59:59+00:00'],
    'exactly deadline' => ['2026-06-23T02:00:00+00:00'],
    'before 06:00 WIB' => ['2026-06-22T22:30:00+00:00'],
]);

it('fails objective check when first upload is after 09:00 WIB', function (): void {
    $result = app(EvaluateObjectiveRules::class)->handle(
        stageThreeArea(firstUploadAt: '2026-06-23T02:00:01+00:00', imageCount: 2),
        stageThreeReportDate(),
    );

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('Upload pertama melewati 09:00 WIB');
});

it('fails objective check when first upload is on the wrong report date', function (): void {
    $result = app(EvaluateObjectiveRules::class)->handle(
        stageThreeArea(firstUploadAt: '2026-06-22T01:00:00+00:00', imageCount: 2),
        stageThreeReportDate(),
    );

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('Tanggal upload pertama tidak sesuai tanggal laporan');
});

it('fails objective check when fewer than two image attachments exist', function (): void {
    $result = app(EvaluateObjectiveRules::class)->handle(
        stageThreeArea(firstUploadAt: '2026-06-23T01:00:00+00:00', imageCount: 1),
        stageThreeReportDate(),
    );

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('Foto kurang: 1 dari minimum 2');
});

it('persists objective failures as final Bermasalah results and skips passed areas', function (): void {
    fakeStageThreeBasecampResponses([
        stageThreeTodoPayload(id: 1001, content: 'BD'),
        stageThreeTodoPayload(id: 1002, content: 'Lobby'),
    ], [
        1001 => [
            stageThreeCommentPayload(id: 1, createdAt: '2026-06-23T01:00:00.000Z', attachments: [
                stageThreeAttachmentPayload(id: 'bd-1'),
                stageThreeAttachmentPayload(id: 'bd-2'),
            ]),
        ],
        1002 => [
            stageThreeCommentPayload(id: 2, createdAt: '2026-06-23T02:05:00.000Z', attachments: [
                stageThreeAttachmentPayload(id: 'lobby-1'),
                stageThreeAttachmentPayload(id: 'ignored-pdf', contentType: 'application/pdf'),
                stageThreeAttachmentPayload(id: 'lobby-2'),
            ]),
        ],
    ]);

    $summary = app(RunObjectiveAudit::class)->handle(stageThreeReportDate());

    expect($summary['areas_checked'])->toBe(2)
        ->and($summary['objective_passed'])->toBe(1)
        ->and($summary['objective_failed'])->toBe(1)
        ->and($summary['failures_persisted'])->toBe(1)
        ->and(DailyAreaAudit::query()->count())->toBe(1);

    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->area_identity)->toBe('1002')
        ->and($audit->area_name)->toBe('Lobby')
        ->and($audit->photo_count)->toBe(2)
        ->and($audit->system_check_passed)->toBeFalse()
        ->and($audit->status)->toBe(AuditStatus::Bermasalah)
        ->and($audit->reason)->toBe('Upload pertama melewati 09:00 WIB')
        ->and($audit->basecamp_todo_url)->toBe('https://app.basecamp.com/4888518/buckets/47333489/todos/1002');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'openai.com') || str_contains($request->url(), 'notion.com'));
});

it('does not duplicate finalized objective failures on rerun', function (): void {
    fakeStageThreeBasecampResponses([
        stageThreeTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => [
            stageThreeCommentPayload(id: 1, createdAt: '2026-06-23T02:05:00.000Z', attachments: [
                stageThreeAttachmentPayload(id: 'bd-1'),
                stageThreeAttachmentPayload(id: 'bd-2'),
            ]),
        ],
    ]);

    $first = app(RunObjectiveAudit::class)->handle(stageThreeReportDate());
    $second = app(RunObjectiveAudit::class)->handle(stageThreeReportDate());

    expect($first['failures_persisted'])->toBe(1)
        ->and($second['failures_persisted'])->toBe(0)
        ->and(DailyAreaAudit::query()->count())->toBe(1);
});

it('persists a Bermasalah finding when the dated list is missing before check time', function (): void {
    fakeStageThreeBasecampResponses([], []);

    $summary = app(RunObjectiveAudit::class)->handle(stageThreeReportDate());

    expect($summary['areas_checked'])->toBe(0)
        ->and($summary['objective_failed'])->toBe(1)
        ->and($summary['failures_persisted'])->toBe(1)
        ->and($summary['missing_dated_todolist'])->toBeTrue()
        ->and(DailyAreaAudit::query()->count())->toBe(1);

    $audit = DailyAreaAudit::query()->firstOrFail();

    expect($audit->area_identity)->toBe('missing-dated-list:2026-06-23')
        ->and($audit->area_name)->toBe('Todo List Harian')
        ->and($audit->basecamp_todo_id)->toBe('missing-dated-list:2026-06-23')
        ->and($audit->basecamp_todo_url)->toBe('https://app.basecamp.com/4888518/projects/47333489')
        ->and($audit->status)->toBe(AuditStatus::Bermasalah)
        ->and($audit->reason)->toBe('To-do list belum dibuat sebelum jam pengecekan');
});

it('runs objective audit through Artisan command', function (): void {
    fakeStageThreeBasecampResponses([
        stageThreeTodoPayload(id: 1001, content: 'BD'),
    ], [
        1001 => [
            stageThreeCommentPayload(id: 1, createdAt: '2026-06-23T02:05:00.000Z', attachments: [
                stageThreeAttachmentPayload(id: 'bd-1'),
                stageThreeAttachmentPayload(id: 'bd-2'),
            ]),
        ],
    ]);

    $this->artisan('kpus-ga-hw:objective-audit --report-date=2026-06-23')
        ->assertExitCode(0);

    expect(DailyAreaAudit::query()->count())->toBe(1);
});

function stageThreeReportDate(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-23', 'Asia/Jakarta');
}

/** @return array<string, mixed> */
function stageThreeArea(string $firstUploadAt, int $imageCount): array
{
    return [
        'area_external_id' => '1001',
        'area_name' => 'BD',
        'todo_url' => 'https://app.basecamp.com/4888518/buckets/47333489/todos/1001',
        'image_count' => $imageCount,
        'first_upload_at' => $firstUploadAt,
    ];
}

/**
 * @param  list<array<string, mixed>>  $todos
 * @param  array<int, list<array<string, mixed>>>  $commentsByTodo
 */
function fakeStageThreeBasecampResponses(array $todos, array $commentsByTodo): void
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

    $todolists = $todos === []
        ? [stageThreeTodosetTodolistPayload(id: 2000, title: 'MASTER (JANGAN DIGANTI)')]
        : [stageThreeTodosetTodolistPayload(id: 10018362569, title: '23-06-2026')];

    $responses = [
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(stageThreeProjectPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json' => Http::response(stageThreeTodosetPayload($todolists)),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json' => Http::response(stageThreeTodolistPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569/todos.json' => Http::response($todos),
    ];

    foreach ($commentsByTodo as $todoId => $comments) {
        $responses["https://3.basecampapi.com/4888518/buckets/47333489/recordings/{$todoId}/comments.json"] = Http::response($comments);
    }

    Http::fake($responses);
}

/** @return array<string, mixed> */
function stageThreeProjectPayload(): array
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

/**
 * @param  list<array<string, mixed>>  $todolists
 * @return array<string, mixed>
 */
function stageThreeTodosetPayload(array $todolists): array
{
    return [
        'id' => 9905161959,
        'type' => 'Todoset',
        'bucket' => [
            'id' => 47333489,
            'name' => 'KPUS GA HW',
            'type' => 'Project',
        ],
        'todolists' => $todolists,
        'todolists_url' => 'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959/todolists.json',
    ];
}

/** @return array<string, mixed> */
function stageThreeTodosetTodolistPayload(int $id, string $title): array
{
    return [
        'id' => $id,
        'title' => $title,
        'url' => "https://3.basecampapi.com/4888518/buckets/47333489/todolists/{$id}.json",
        'app_url' => "https://app.basecamp.com/4888518/buckets/47333489/todolists/{$id}",
    ];
}

/** @return array<string, mixed> */
function stageThreeTodolistPayload(): array
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
function stageThreeTodoPayload(int $id, string $content): array
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
function stageThreeCommentPayload(int $id, string $createdAt, array $attachments): array
{
    return [
        'id' => $id,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'type' => 'Comment',
        'parent' => [
            'id' => 10018362592,
            'title' => 'BD',
            'type' => 'Todo',
        ],
        'content_attachments' => $attachments,
    ];
}

/** @return array<string, mixed> */
function stageThreeAttachmentPayload(string $id, string $contentType = 'image/png'): array
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

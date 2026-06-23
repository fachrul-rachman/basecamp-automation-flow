<?php

use App\Core\Shared\Basecamp\Contracts\BasecampClient;
use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Application\Exceptions\DatedTodolistNotFoundException;
use App\Modules\KpusGaHw\Application\Exceptions\DuplicateDatedTodolistException;
use App\Modules\KpusGaHw\Application\Services\BuildReadOnlyAuditInput;
use App\Modules\KpusGaHw\Application\Services\DateTitleParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('parses accepted KPUS GA HW date-list title formats and ignores non-date titles', function (): void {
    $parser = app(DateTitleParser::class);

    expect($parser->parse('22 Juni 2026')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('22-06-2026')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('22 - 06 - 2026')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('22.06.2026')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('22 / 06 / 2026')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('22-06-26')?->toDateString())->toBe('2026-06-22')
        ->and($parser->parse('MASTER (JANGAN DIGANTI)'))->toBeNull()
        ->and($parser->parse('Master (Messanger)'))->toBeNull();
});

it('follows Basecamp Link pagination for collection endpoints', function (): void {
    Http::fake([
        'https://example.test/todos?page=1' => Http::response([todoPayload(id: 1, content: 'BD')], 200, [
            'Link' => '<https://example.test/todos?page=2>; rel="next"',
        ]),
        'https://example.test/todos?page=2' => Http::response([todoPayload(id: 2, content: 'Lobby')]),
    ]);

    $todos = app(BasecampClient::class)->listTodos('https://example.test/todos?page=1');

    expect($todos)->toHaveCount(2)
        ->and($todos[0]->id)->toBe('1')
        ->and($todos[1]->id)->toBe('2');
});

it('builds normalized read-only audit input from verified Basecamp payload shapes', function (): void {
    fakeBasecampReadModelResponses();

    $input = app(BuildReadOnlyAuditInput::class)->handle(reportDate());

    expect($input['project']['account_id'])->toBe('4888518')
        ->and($input['project']['project_id'])->toBe('47333489')
        ->and($input['project']['todoset_id'])->toBe('9905161959')
        ->and($input['dated_todolist']['title'])->toBe('23-06-2026')
        ->and($input['areas'])->toHaveCount(1)
        ->and($input['areas'][0]['area_external_id'])->toBe('10018362592')
        ->and($input['areas'][0]['area_name'])->toBe('BD from content')
        ->and($input['areas'][0]['todo_url'])->toBe('https://app.basecamp.com/4888518/buckets/47333489/todos/10018362592')
        ->and($input['areas'][0]['comments_url'])->toBe('https://3.basecampapi.com/4888518/buckets/47333489/recordings/10018362592/comments.json')
        ->and($input['areas'][0]['image_count'])->toBe(2)
        ->and($input['areas'][0]['first_upload_at'])->toBe('2026-06-23T01:01:00+00:00')
        ->and($input['areas'][0]['images'][0]['attachment_id'])->toBe('img-early')
        ->and($input['areas'][0]['images'][1]['attachment_id'])->toBe('img-late')
        ->and(BasecampProject::query()->where('basecamp_project_id', '47333489')->exists())->toBeTrue();
});

it('fails read-only input generation when no dated list matches the report date', function (): void {
    fakeBasecampReadModelResponses(todolists: [
        todosetTodolistPayload(id: 100, title: 'MASTER (JANGAN DIGANTI)'),
    ]);

    expect(fn () => app(BuildReadOnlyAuditInput::class)->handle(reportDate()))
        ->toThrow(DatedTodolistNotFoundException::class);
});

it('fails read-only input generation when multiple lists resolve to the same report date', function (): void {
    fakeBasecampReadModelResponses(todolists: [
        todosetTodolistPayload(id: 100, title: '23-06-2026'),
        todosetTodolistPayload(id: 101, title: '23 / 06 / 2026'),
    ]);

    expect(fn () => app(BuildReadOnlyAuditInput::class)->handle(reportDate()))
        ->toThrow(DuplicateDatedTodolistException::class);
});

it('prints normalized audit input through the read-only Artisan command', function (): void {
    fakeBasecampReadModelResponses();

    $this->artisan('kpus-ga-hw:basecamp-input --report-date=2026-06-23')
        ->assertExitCode(0);

    Http::assertNotSent(function ($request): bool {
        return str_contains($request->url(), 'openai.com') || str_contains($request->url(), 'notion.com');
    });
});

function reportDate(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 6, 23, 0, 0, 0, 'Asia/Jakarta');
}

/**
 * @param  list<array<string, mixed>>|null  $todolists
 */
function fakeBasecampReadModelResponses(?array $todolists = null): void
{
    $todolists ??= [
        todosetTodolistPayload(id: 10018362569, title: '23-06-2026'),
        todosetTodolistPayload(id: 9918899649, title: 'MASTER (JANGAN DIGANTI)'),
    ];

    Http::fake([
        'https://3.basecampapi.com/4888518/projects/47333489.json' => Http::response(projectPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todosets/9905161959.json' => Http::response(todosetPayload($todolists)),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569.json' => Http::response(todolistPayload()),
        'https://3.basecampapi.com/4888518/buckets/47333489/todolists/10018362569/todos.json' => Http::response([
            todoPayload(id: 10018362592, content: 'BD from content'),
        ]),
        'https://3.basecampapi.com/4888518/buckets/47333489/recordings/10018362592/comments.json' => Http::response([
            commentPayload(id: 2, createdAt: '2026-06-23T01:05:02.324Z', attachments: [
                attachmentPayload(id: 'file-not-image', contentType: 'application/pdf'),
                attachmentPayload(id: 'img-late'),
            ]),
            commentPayload(id: 1, createdAt: '2026-06-23T01:01:00.000Z', attachments: [
                attachmentPayload(id: 'img-early'),
            ]),
        ]),
    ]);
}

/** @return array<string, mixed> */
function projectPayload(): array
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
function todosetPayload(array $todolists): array
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
function todosetTodolistPayload(int $id, string $title): array
{
    return [
        'id' => $id,
        'title' => $title,
        'url' => "https://3.basecampapi.com/4888518/buckets/47333489/todolists/{$id}.json",
        'app_url' => "https://app.basecamp.com/4888518/buckets/47333489/todolists/{$id}",
    ];
}

/** @return array<string, mixed> */
function todolistPayload(): array
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
function todoPayload(int $id, string $content): array
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
function commentPayload(int $id, string $createdAt, array $attachments): array
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
function attachmentPayload(string $id, string $contentType = 'image/png'): array
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

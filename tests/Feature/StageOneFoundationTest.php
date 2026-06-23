<?php

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Contracts\HolidayProvider;
use App\Core\Shared\Scheduling\Models\Holiday;
use App\Core\Shared\Scheduling\Services\DatabaseHolidayProvider;
use App\Core\Shared\Scheduling\Services\SystemClock;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('binds shared scheduling interfaces to concrete services', function (): void {
    expect(app(Clock::class))->toBeInstanceOf(SystemClock::class)
        ->and(app(HolidayProvider::class))->toBeInstanceOf(DatabaseHolidayProvider::class);
});

it('persists registered Basecamp projects and database-backed holidays', function (): void {
    $project = BasecampProject::factory()->create([
        'basecamp_account_id' => '4888518',
        'basecamp_project_id' => '47333489',
        'name' => 'KPUS GA HW',
        'workflow_type' => 'kpus_ga_hw',
    ]);

    Holiday::factory()->create([
        'holiday_date' => '2026-06-22',
        'name' => 'Configured holiday',
    ]);

    expect($project->fresh())
        ->basecamp_account_id->toBe('4888518')
        ->basecamp_project_id->toBe('47333489')
        ->active->toBeTrue()
        ->and(app(HolidayProvider::class)->isHoliday(CarbonImmutable::parse('2026-06-22', 'Asia/Jakarta')))->toBeTrue()
        ->and(app(HolidayProvider::class)->isHoliday(CarbonImmutable::parse('2026-06-23', 'Asia/Jakarta')))->toBeFalse();
});

it('stores final daily audit state with enum casts and Notion delivery metadata', function (): void {
    $audit = DailyAreaAudit::factory()->create([
        'status' => AuditStatus::NeedReview,
        'notion_delivery_status' => NotionDeliveryStatus::Failed,
        'ai_reasons' => ['AI gagal membaca foto'],
        'last_notion_error' => 'timeout',
    ])->fresh();

    expect($audit->status)->toBe(AuditStatus::NeedReview)
        ->and($audit->notion_delivery_status)->toBe(NotionDeliveryStatus::Failed)
        ->and($audit->ai_reasons)->toBe(['AI gagal membaca foto'])
        ->and($audit->last_notion_error)->toBe('timeout');
});

it('enforces one audit result per project, report date, and area identity', function (): void {
    $project = BasecampProject::factory()->create();

    DailyAreaAudit::factory()->create([
        'project_id' => $project->id,
        'report_date' => '2026-06-22',
        'area_identity' => 'master:123',
    ]);

    expect(fn () => DailyAreaAudit::factory()->create([
        'project_id' => $project->id,
        'report_date' => '2026-06-22',
        'area_identity' => 'master:123',
    ]))->toThrow(QueryException::class);
});

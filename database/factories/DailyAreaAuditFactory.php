<?php

namespace Database\Factories;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailyAreaAudit> */
class DailyAreaAuditFactory extends Factory
{
    protected $model = DailyAreaAudit::class;

    public function definition(): array
    {
        return [
            'project_id' => BasecampProject::factory(),
            'report_date' => today(),
            'area_identity' => 'master:'.fake()->unique()->numberBetween(100000, 999999),
            'area_name' => fake()->words(3, true),
            'basecamp_todo_id' => (string) fake()->numberBetween(100000, 999999),
            'basecamp_todo_url' => fake()->url(),
            'photo_count' => 2,
            'first_upload_at' => now(),
            'system_check_passed' => true,
            'ai_result' => null,
            'ai_reasons' => null,
            'status' => AuditStatus::Baik,
            'reason' => 'Sesuai ketentuan',
            'finalized_at' => now(),
            'notion_delivery_status' => NotionDeliveryStatus::Pending,
            'notion_page_id' => null,
            'notion_delivered_at' => null,
            'notion_attempts' => 0,
            'last_notion_error' => null,
        ];
    }
}

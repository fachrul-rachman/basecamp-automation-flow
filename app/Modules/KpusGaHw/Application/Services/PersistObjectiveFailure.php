<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Application\Data\ObjectiveAuditResult;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;

class PersistObjectiveFailure
{
    public function handle(BasecampProject $project, CarbonImmutable $reportDate, ObjectiveAuditResult $result): DailyAreaAudit
    {
        return DailyAreaAudit::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'report_date' => $reportDate->startOfDay(),
                'area_identity' => $result->areaExternalId,
            ],
            [
                'area_name' => $result->areaName,
                'basecamp_todo_id' => $result->areaExternalId,
                'basecamp_todo_url' => $result->todoUrl,
                'photo_count' => $result->photoCount,
                'first_upload_at' => $result->firstUploadAt,
                'system_check_passed' => false,
                'ai_result' => null,
                'ai_reasons' => null,
                'status' => AuditStatus::Bermasalah,
                'reason' => $result->reason,
                'finalized_at' => CarbonImmutable::now((string) config('kpus-ga-hw.timezone')),
                'notion_delivery_status' => NotionDeliveryStatus::Pending,
                'notion_page_id' => null,
                'notion_attempts' => 0,
                'last_notion_error' => null,
            ],
        );
    }
}

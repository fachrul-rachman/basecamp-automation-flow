<?php

namespace App\Modules\KpusGaHw\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class RunDailyAudit
{
    public function __construct(
        private readonly RunAiReviewAudit $audit,
        private readonly PublishPendingAuditResultsToNotion $publisher,
    ) {}

    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $reportDate): array
    {
        Log::info('kpus_ga_hw.daily_audit.started', [
            'report_date' => $reportDate->toDateString(),
        ]);

        $auditSummary = $this->audit->handle($reportDate);
        $publishSummary = $this->publisher->handle($reportDate->toDateString());

        $summary = [
            'report_date' => $reportDate->toDateString(),
            'audit' => $auditSummary,
            'notion' => $publishSummary,
        ];

        Log::info('kpus_ga_hw.daily_audit.finished', [
            'report_date' => $summary['report_date'],
            'areas_checked' => $auditSummary['areas_checked'] ?? null,
            'objective_failed' => $auditSummary['objective_failed'] ?? null,
            'ai_reviewed' => $auditSummary['ai_reviewed'] ?? null,
            'baik' => $auditSummary['baik'] ?? null,
            'need_review' => $auditSummary['need_review'] ?? null,
            'bermasalah' => $auditSummary['bermasalah'] ?? null,
            'notion_checked' => $publishSummary['checked'],
            'notion_delivered' => $publishSummary['delivered'],
            'notion_failed' => $publishSummary['failed'],
        ]);

        if ($publishSummary['failed'] > 0) {
            Log::warning('kpus_ga_hw.notion_delivery.pending_failures', [
                'report_date' => $summary['report_date'],
                'failed' => $publishSummary['failed'],
            ]);
        }

        return $summary;
    }
}

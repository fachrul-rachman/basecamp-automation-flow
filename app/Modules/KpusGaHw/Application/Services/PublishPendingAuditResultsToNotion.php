<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;

class PublishPendingAuditResultsToNotion
{
    public function __construct(private readonly PublishAuditResultToNotion $publisher) {}

    /** @return array<string, int> */
    public function handle(?string $reportDate = null): array
    {
        $query = DailyAreaAudit::query()
            ->whereNull('notion_page_id')
            ->whereIn('notion_delivery_status', [
                NotionDeliveryStatus::Pending->value,
                NotionDeliveryStatus::Failed->value,
            ])
            ->orderBy('id');

        if ($reportDate !== null) {
            $query->whereDate('report_date', $reportDate);
        }

        $summary = [
            'checked' => 0,
            'delivered' => 0,
            'failed' => 0,
        ];

        $query->each(function (DailyAreaAudit $audit) use (&$summary): void {
            $summary['checked']++;
            $fresh = $this->publisher->handle($audit);

            if ($fresh->notion_delivery_status === NotionDeliveryStatus::Delivered) {
                $summary['delivered']++;

                return;
            }

            $summary['failed']++;
        });

        return $summary;
    }
}

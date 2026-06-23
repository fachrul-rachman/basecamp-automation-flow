<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Notion\Data\NotionCreatePageRequest;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use InvalidArgumentException;

class BuildKpusGaHwNotionPageRequest
{
    public function handle(DailyAreaAudit $audit): NotionCreatePageRequest
    {
        $status = $audit->status;

        if (! $status instanceof AuditStatus) {
            throw new InvalidArgumentException('Invalid audit status for Notion delivery.');
        }

        return new NotionCreatePageRequest(
            dataSourceId: (string) config('services.notion.data_source_id'),
            properties: [
                'Area' => [
                    'title' => [
                        [
                            'text' => [
                                'content' => $audit->area_name,
                            ],
                        ],
                    ],
                ],
                'Tanggal' => [
                    'date' => [
                        'start' => $audit->report_date->toDateString(),
                    ],
                ],
                'Alasan' => [
                    'rich_text' => [
                        [
                            'text' => [
                                'content' => $audit->reason,
                            ],
                        ],
                    ],
                ],
                'Bukti' => [
                    'url' => $audit->basecamp_todo_url,
                ],
                'Status' => [
                    'status' => [
                        'name' => $status->value,
                    ],
                ],
                'Validator Status' => [
                    'status' => [
                        'name' => $this->validatorStatus($status),
                    ],
                ],
                'Validator Notes' => [
                    'rich_text' => [],
                ],
            ],
        );
    }

    private function validatorStatus(AuditStatus $status): string
    {
        return match ($status) {
            AuditStatus::Baik => 'Check',
            AuditStatus::Bermasalah, AuditStatus::NeedReview => 'Uncheck',
        };
    }
}

<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Notion\Contracts\NotionClient;
use App\Core\Shared\Support\Contracts\Sleeper;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishAuditResultToNotion
{
    private const MAX_ATTEMPTS = 3;

    private const RETRY_DELAY_SECONDS = 5;

    public function __construct(
        private readonly NotionClient $notion,
        private readonly BuildKpusGaHwNotionPageRequest $requestBuilder,
        private readonly Sleeper $sleeper,
    ) {}

    public function handle(DailyAreaAudit $audit): DailyAreaAudit
    {
        if ($audit->notion_page_id !== null) {
            Log::info('kpus_ga_hw.notion_delivery.skipped_existing_page', [
                'audit_id' => $audit->id,
                'report_date' => $audit->report_date?->toDateString(),
                'area_identity' => $audit->area_identity,
            ]);

            return $audit;
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $this->notion->createPage($this->requestBuilder->handle($audit));

                $audit->forceFill([
                    'notion_delivery_status' => NotionDeliveryStatus::Delivered,
                    'notion_page_id' => $response->pageId,
                    'notion_delivered_at' => CarbonImmutable::now((string) config('kpus-ga-hw.timezone')),
                    'notion_attempts' => $audit->notion_attempts + 1,
                    'last_notion_error' => null,
                ])->save();

                Log::info('kpus_ga_hw.notion_delivery.delivered', [
                    'audit_id' => $audit->id,
                    'report_date' => $audit->report_date?->toDateString(),
                    'area_identity' => $audit->area_identity,
                    'attempt' => $audit->notion_attempts,
                    'notion_page_id' => $response->pageId,
                    'notion_request_id' => $response->requestId,
                ]);

                return $audit->fresh();
            } catch (Throwable $exception) {
                $lastError = $this->sanitize($exception->getMessage());

                $audit->forceFill([
                    'notion_delivery_status' => NotionDeliveryStatus::Failed,
                    'notion_attempts' => $audit->notion_attempts + 1,
                    'last_notion_error' => $lastError,
                ])->save();

                Log::warning('kpus_ga_hw.notion_delivery.failed_attempt', [
                    'audit_id' => $audit->id,
                    'report_date' => $audit->report_date?->toDateString(),
                    'area_identity' => $audit->area_identity,
                    'attempt' => $audit->notion_attempts,
                    'error' => $lastError,
                ]);

                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->sleeper->sleepSeconds(self::RETRY_DELAY_SECONDS);
                }
            }
        }

        return $audit->fresh();
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/secret_[A-Za-z0-9_\-]+/i', 'secret_[redacted]', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}

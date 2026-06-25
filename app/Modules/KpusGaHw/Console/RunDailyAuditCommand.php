<?php

namespace App\Modules\KpusGaHw\Console;

use App\Modules\KpusGaHw\Application\Exceptions\AuditDateSkippedException;
use App\Modules\KpusGaHw\Application\Services\DetermineReportDate;
use App\Modules\KpusGaHw\Application\Services\RunDailyAudit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDailyAuditCommand extends Command
{
    protected $signature = 'kpus-ga-hw:daily-audit {--report-date= : Optional report date in YYYY-MM-DD format}';

    protected $description = 'Run the full KPUS GA HW daily audit and publish finalized results to Notion.';

    public function handle(RunDailyAudit $audit, DetermineReportDate $determineReportDate): int
    {
        try {
            $reportDate = $this->reportDate($determineReportDate);
            $summary = $audit->handle($reportDate);
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (AuditDateSkippedException $exception) {
            $this->line(json_encode([
                'skipped' => true,
                'reason' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('kpus_ga_hw.daily_audit.failed', [
                'report_date' => isset($reportDate) ? $reportDate->toDateString() : null,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function reportDate(DetermineReportDate $determineReportDate): CarbonImmutable
    {
        $value = $this->option('report-date');

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, (string) config('kpus-ga-hw.timezone'))->startOfDay();
        }

        return $determineReportDate->handle();
    }
}

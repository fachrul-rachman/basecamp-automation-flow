<?php

namespace App\Modules\KpusGaHw\Console;

use App\Modules\KpusGaHw\Application\Services\DetermineReportDate;
use App\Modules\KpusGaHw\Application\Services\RunAiReviewAudit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class RunAiReviewAuditCommand extends Command
{
    protected $signature = 'kpus-ga-hw:ai-review {--report-date= : Report date in YYYY-MM-DD format}';

    protected $description = 'Run KPUS GA HW objective audit and AI review for objective-passed areas.';

    public function handle(RunAiReviewAudit $audit, DetermineReportDate $determineReportDate): int
    {
        try {
            $summary = $audit->handle($this->reportDate($determineReportDate));
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
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

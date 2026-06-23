<?php

namespace App\Modules\KpusGaHw\Console;

use App\Modules\KpusGaHw\Application\Services\PublishPendingAuditResultsToNotion;
use Illuminate\Console\Command;
use Throwable;

class PublishNotionResultsCommand extends Command
{
    protected $signature = 'kpus-ga-hw:publish-notion {--report-date= : Optional report date in YYYY-MM-DD format}';

    protected $description = 'Publish pending or failed KPUS GA HW audit results to Notion.';

    public function handle(PublishPendingAuditResultsToNotion $publisher): int
    {
        try {
            $reportDate = $this->option('report-date');
            $summary = $publisher->handle(is_string($reportDate) && $reportDate !== '' ? $reportDate : null);
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

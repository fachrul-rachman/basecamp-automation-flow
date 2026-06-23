<?php

namespace App\Modules\KpusGaHw\Console;

use App\Modules\KpusGaHw\Application\Services\BuildReadOnlyAuditInput;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class PrintBasecampAuditInputCommand extends Command
{
    protected $signature = 'kpus-ga-hw:basecamp-input {--report-date= : Report date in YYYY-MM-DD format}';

    protected $description = 'Print normalized read-only Basecamp audit input for KPUS GA HW.';

    public function handle(BuildReadOnlyAuditInput $builder): int
    {
        $reportDate = $this->reportDate();

        try {
            $this->line(json_encode($builder->handle($reportDate), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function reportDate(): CarbonImmutable
    {
        $value = $this->option('report-date');

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, (string) config('kpus-ga-hw.timezone'))->startOfDay();
        }

        return CarbonImmutable::now((string) config('kpus-ga-hw.timezone'))->startOfDay();
    }
}

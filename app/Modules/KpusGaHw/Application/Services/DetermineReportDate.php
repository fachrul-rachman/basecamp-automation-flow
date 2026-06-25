<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Contracts\HolidayProvider;
use App\Modules\KpusGaHw\Application\Exceptions\AuditDateSkippedException;
use Carbon\CarbonImmutable;

class DetermineReportDate
{
    public function __construct(
        private readonly Clock $clock,
        private readonly HolidayProvider $holidays,
    ) {}

    public function handle(?CarbonImmutable $runDate = null): CarbonImmutable
    {
        $timezone = (string) config('kpus-ga-hw.timezone');
        $date = ($runDate ?? $this->clock->now())->setTimezone($timezone)->startOfDay();

        if ($date->isWeekend()) {
            throw AuditDateSkippedException::weekend($date->toDateString());
        }

        if ($this->holidays->isHoliday($date)) {
            throw AuditDateSkippedException::holiday($date->toDateString());
        }

        return $date;
    }
}

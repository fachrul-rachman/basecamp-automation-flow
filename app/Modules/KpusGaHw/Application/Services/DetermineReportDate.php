<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Contracts\HolidayProvider;
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
        $date = ($runDate ?? $this->clock->now())->setTimezone($timezone)->startOfDay()->subDay();

        while (! $this->isBusinessDay($date)) {
            $date = $date->subDay();
        }

        return $date;
    }

    private function isBusinessDay(CarbonImmutable $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->holidays->isHoliday($date);
    }
}

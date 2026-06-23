<?php

namespace App\Core\Shared\Scheduling\Services;

use App\Core\Shared\Scheduling\Contracts\HolidayProvider;
use App\Core\Shared\Scheduling\Models\Holiday;
use Carbon\CarbonImmutable;

class DatabaseHolidayProvider implements HolidayProvider
{
    public function isHoliday(CarbonImmutable $date): bool
    {
        return Holiday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->exists();
    }
}

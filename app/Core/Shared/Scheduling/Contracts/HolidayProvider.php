<?php

namespace App\Core\Shared\Scheduling\Contracts;

use Carbon\CarbonImmutable;

interface HolidayProvider
{
    public function isHoliday(CarbonImmutable $date): bool;
}

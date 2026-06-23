<?php

namespace App\Core\Shared\Scheduling\Services;

use App\Core\Shared\Scheduling\Contracts\Clock;
use Carbon\CarbonImmutable;

class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Jakarta');
    }
}

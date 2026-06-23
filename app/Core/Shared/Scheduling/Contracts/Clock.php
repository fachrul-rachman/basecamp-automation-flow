<?php

namespace App\Core\Shared\Scheduling\Contracts;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}

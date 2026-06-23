<?php

namespace App\Core\Shared\Support\Contracts;

interface Sleeper
{
    public function sleepSeconds(int $seconds): void;
}

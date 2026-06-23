<?php

namespace App\Core\Shared\Support\Services;

use App\Core\Shared\Support\Contracts\Sleeper;

class NativeSleeper implements Sleeper
{
    public function sleepSeconds(int $seconds): void
    {
        sleep($seconds);
    }
}

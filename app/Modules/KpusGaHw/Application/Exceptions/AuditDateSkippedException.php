<?php

namespace App\Modules\KpusGaHw\Application\Exceptions;

use RuntimeException;

class AuditDateSkippedException extends RuntimeException
{
    public static function weekend(string $date): self
    {
        return new self("Audit skipped for {$date}: weekend.");
    }

    public static function holiday(string $date): self
    {
        return new self("Audit skipped for {$date}: configured holiday.");
    }
}

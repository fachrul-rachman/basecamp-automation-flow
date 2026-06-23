<?php

namespace App\Modules\KpusGaHw\Application\Exceptions;

use RuntimeException;

class DatedTodolistNotFoundException extends RuntimeException
{
    public static function forDate(string $reportDate): self
    {
        return new self("No dated to-do list found for {$reportDate}.");
    }
}

<?php

namespace App\Modules\KpusGaHw\Application\Exceptions;

use RuntimeException;

class DuplicateDatedTodolistException extends RuntimeException
{
    /** @param list<string> $titles */
    public static function forDate(string $reportDate, array $titles): self
    {
        return new self("Multiple dated to-do lists found for {$reportDate}: ".implode(', ', $titles));
    }
}

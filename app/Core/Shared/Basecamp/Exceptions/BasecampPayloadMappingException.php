<?php

namespace App\Core\Shared\Basecamp\Exceptions;

use RuntimeException;

class BasecampPayloadMappingException extends RuntimeException
{
    public static function missingField(string $field): self
    {
        return new self("Basecamp payload missing required field: {$field}");
    }

    public static function configuration(string $message): self
    {
        return new self($message);
    }
}

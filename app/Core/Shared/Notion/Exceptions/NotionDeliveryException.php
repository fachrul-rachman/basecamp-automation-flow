<?php

namespace App\Core\Shared\Notion\Exceptions;

use RuntimeException;

class NotionDeliveryException extends RuntimeException
{
    public static function failed(string $message): self
    {
        return new self($message);
    }
}

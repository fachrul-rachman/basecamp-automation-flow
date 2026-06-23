<?php

namespace App\Core\Shared\Basecamp\Data;

use App\Core\Shared\Basecamp\Exceptions\BasecampPayloadMappingException;
use Carbon\CarbonImmutable;

final class BasecampPayload
{
    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value) || $value === '') {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function int(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function nullableInt(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function bool(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (! is_bool($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function carbon(array $payload, string $key): CarbonImmutable
    {
        return CarbonImmutable::parse(self::string($payload, $key));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function object(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function list(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw BasecampPayloadMappingException::missingField($key);
        }

        return array_values($value);
    }
}

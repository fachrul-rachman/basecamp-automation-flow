<?php

namespace App\Core\Shared\OpenAI\Data;

final readonly class VisionReviewResponse
{
    /**
     * @param  array<string, mixed>|null  $json
     */
    private function __construct(
        public bool $successful,
        public ?array $json,
        public ?string $failureReason,
    ) {}

    /** @param array<string, mixed> $json */
    public static function success(array $json): self
    {
        return new self(true, $json, null);
    }

    public static function failure(string $reason): self
    {
        return new self(false, null, $reason);
    }
}

<?php

namespace App\Core\Shared\OpenAI\Data;

final readonly class VisionReviewRequest
{
    /**
     * @param  list<string>  $imageUrls
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $developerPrompt,
        public string $userPrompt,
        public array $imageUrls,
        public array $schema,
        public string $schemaName,
    ) {}
}

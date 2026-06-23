<?php

namespace App\Core\Shared\Basecamp\Data;

final readonly class TodosetTodolistData
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $appUrl,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            title: BasecampPayload::string($payload, 'title'),
            url: BasecampPayload::string($payload, 'url'),
            appUrl: BasecampPayload::string($payload, 'app_url'),
        );
    }
}

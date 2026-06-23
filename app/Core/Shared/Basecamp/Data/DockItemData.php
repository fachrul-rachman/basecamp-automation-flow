<?php

namespace App\Core\Shared\Basecamp\Data;

final readonly class DockItemData
{
    public function __construct(
        public string $id,
        public string $title,
        public string $name,
        public bool $enabled,
        public string $url,
        public string $appUrl,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            title: BasecampPayload::string($payload, 'title'),
            name: BasecampPayload::string($payload, 'name'),
            enabled: BasecampPayload::bool($payload, 'enabled'),
            url: BasecampPayload::string($payload, 'url'),
            appUrl: BasecampPayload::string($payload, 'app_url'),
        );
    }
}

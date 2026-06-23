<?php

namespace App\Core\Shared\Basecamp\Data;

use App\Core\Shared\Basecamp\Exceptions\BasecampPayloadMappingException;

final readonly class BasecampProjectData
{
    /** @param list<DockItemData> $dock */
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        public string $appUrl,
        public array $dock,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            name: BasecampPayload::string($payload, 'name'),
            url: BasecampPayload::string($payload, 'url'),
            appUrl: BasecampPayload::string($payload, 'app_url'),
            dock: array_map(
                fn (array $item): DockItemData => DockItemData::fromPayload($item),
                BasecampPayload::list($payload, 'dock'),
            ),
        );
    }

    public function enabledTodosetDockItem(): DockItemData
    {
        foreach ($this->dock as $dockItem) {
            if ($dockItem->name === 'todoset' && $dockItem->enabled) {
                return $dockItem;
            }
        }

        throw BasecampPayloadMappingException::configuration('No enabled todoset dock item found.');
    }
}

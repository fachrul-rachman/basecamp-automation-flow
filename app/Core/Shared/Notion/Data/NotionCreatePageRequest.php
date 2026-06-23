<?php

namespace App\Core\Shared\Notion\Data;

final readonly class NotionCreatePageRequest
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $dataSourceId,
        public array $properties,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'parent' => [
                'type' => 'data_source_id',
                'data_source_id' => $this->dataSourceId,
            ],
            'properties' => $this->properties,
        ];
    }
}

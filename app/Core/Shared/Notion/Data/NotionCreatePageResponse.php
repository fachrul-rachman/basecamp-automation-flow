<?php

namespace App\Core\Shared\Notion\Data;

final readonly class NotionCreatePageResponse
{
    public function __construct(
        public string $pageId,
        public ?string $url = null,
        public ?string $requestId = null,
    ) {}
}

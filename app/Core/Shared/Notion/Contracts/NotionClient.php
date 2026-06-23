<?php

namespace App\Core\Shared\Notion\Contracts;

use App\Core\Shared\Notion\Data\NotionCreatePageRequest;
use App\Core\Shared\Notion\Data\NotionCreatePageResponse;

interface NotionClient
{
    public function createPage(NotionCreatePageRequest $request): NotionCreatePageResponse;
}

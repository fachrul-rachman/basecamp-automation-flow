<?php

namespace App\Core\Shared\Basecamp\Contracts;

interface AttachmentDownloader
{
    public function toImageInput(string $url): string;
}

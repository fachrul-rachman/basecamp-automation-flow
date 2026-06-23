<?php

namespace App\Core\Shared\Basecamp\Services;

use App\Core\Shared\Basecamp\Contracts\AttachmentDownloader;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

class HttpAttachmentDownloader implements AttachmentDownloader
{
    public function __construct(private readonly HttpFactory $http) {}

    public function toImageInput(string $url): string
    {
        try {
            $response = $this->http
                ->withToken((string) config('basecamp.access_token'))
                ->withUserAgent((string) config('basecamp.user_agent'))
                ->get($url)
                ->throw();

            $contentType = $response->header('Content-Type') ?: 'image/jpeg';

            if (! str_starts_with($contentType, 'image/')) {
                return $url;
            }

            return sprintf('data:%s;base64,%s', $contentType, base64_encode($response->body()));
        } catch (Throwable) {
            return $url;
        }
    }
}

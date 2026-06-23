<?php

namespace App\Core\Shared\Notion\Services;

use App\Core\Shared\Notion\Contracts\NotionClient;
use App\Core\Shared\Notion\Data\NotionCreatePageRequest;
use App\Core\Shared\Notion\Data\NotionCreatePageResponse;
use App\Core\Shared\Notion\Exceptions\NotionDeliveryException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

class HttpNotionClient implements NotionClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function createPage(NotionCreatePageRequest $request): NotionCreatePageResponse
    {
        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withToken((string) config('services.notion.token'))
                ->withHeaders([
                    'Notion-Version' => (string) config('services.notion.version', '2025-09-03'),
                ])
                ->post('https://api.notion.com/v1/pages', $request->toPayload())
                ->throw();
        } catch (RequestException $exception) {
            throw NotionDeliveryException::failed($this->safeHttpFailure($exception));
        }

        $pageId = $response->json('id');

        if (! is_string($pageId) || $pageId === '') {
            throw NotionDeliveryException::failed('Notion response missing page ID');
        }

        $url = $response->json('url');

        return new NotionCreatePageResponse(
            pageId: $pageId,
            url: is_string($url) ? $url : null,
            requestId: $response->header('x-request-id'),
        );
    }

    private function safeHttpFailure(RequestException $exception): string
    {
        $status = $exception->response->status();
        $message = $exception->response->json('message');

        if (! is_string($message) || $message === '') {
            $message = $exception->response->json('error.message');
        }

        if (is_string($message) && $message !== '') {
            return "Notion request gagal ({$status}): ".$this->sanitize($message);
        }

        return "Notion request gagal ({$status})";
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/secret_[A-Za-z0-9_\-]+/i', 'secret_[redacted]', $message) ?? $message;

        return mb_substr($message, 0, 240);
    }
}

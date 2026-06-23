<?php

namespace App\Core\Shared\Basecamp\Services;

use App\Core\Shared\Basecamp\Contracts\BasecampClient;
use App\Core\Shared\Basecamp\Data\BasecampProjectData;
use App\Core\Shared\Basecamp\Data\CommentData;
use App\Core\Shared\Basecamp\Data\TodoData;
use App\Core\Shared\Basecamp\Data\TodolistData;
use App\Core\Shared\Basecamp\Data\TodosetData;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class HttpBasecampClient implements BasecampClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function getProject(string $accountId, string $projectId): BasecampProjectData
    {
        $payload = $this->request()->get("https://3.basecampapi.com/{$accountId}/projects/{$projectId}.json")->throw()->json();

        return BasecampProjectData::fromPayload($payload);
    }

    public function getTodoset(string $url): TodosetData
    {
        return TodosetData::fromPayload($this->request()->get($url)->throw()->json());
    }

    public function getTodolist(string $url): TodolistData
    {
        return TodolistData::fromPayload($this->request()->get($url)->throw()->json());
    }

    public function listTodos(string $url): array
    {
        return array_map(
            fn (array $payload): TodoData => TodoData::fromPayload($payload),
            $this->getPaginatedCollection($url),
        );
    }

    public function listComments(string $url): array
    {
        return array_map(
            fn (array $payload): CommentData => CommentData::fromPayload($payload),
            $this->getPaginatedCollection($url),
        );
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->acceptJson()
            ->withToken((string) config('basecamp.access_token'))
            ->withUserAgent((string) config('basecamp.user_agent'));
    }

    /** @return list<array<string, mixed>> */
    private function getPaginatedCollection(string $url): array
    {
        $items = [];

        while ($url !== '') {
            $response = $this->request()->get($url)->throw();
            $payload = $response->json();

            foreach (is_array($payload) ? $payload : [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $url = $this->nextUrl($response) ?? '';
        }

        return $items;
    }

    private function nextUrl(Response $response): ?string
    {
        $link = $response->header('Link');

        if (! is_string($link) || $link === '') {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (str_contains($part, 'rel="next"') && preg_match('/<([^>]+)>/', $part, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}

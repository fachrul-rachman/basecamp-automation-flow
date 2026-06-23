<?php

namespace App\Core\Shared\Basecamp\Data;

final readonly class TodoData
{
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public string $url,
        public string $appUrl,
        public int $commentsCount,
        public string $commentsUrl,
        public string $parentId,
        public string $parentTitle,
        public string $bucketId,
        public bool $completed,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $parent = BasecampPayload::object($payload, 'parent');
        $bucket = BasecampPayload::object($payload, 'bucket');

        return new self(
            id: BasecampPayload::string($payload, 'id'),
            title: BasecampPayload::string($payload, 'title'),
            content: BasecampPayload::nullableString($payload, 'content') ?? '',
            url: BasecampPayload::string($payload, 'url'),
            appUrl: BasecampPayload::string($payload, 'app_url'),
            commentsCount: BasecampPayload::int($payload, 'comments_count'),
            commentsUrl: BasecampPayload::string($payload, 'comments_url'),
            parentId: BasecampPayload::string($parent, 'id'),
            parentTitle: BasecampPayload::string($parent, 'title'),
            bucketId: BasecampPayload::string($bucket, 'id'),
            completed: BasecampPayload::bool($payload, 'completed'),
        );
    }

    public function areaName(): string
    {
        $content = trim($this->content);

        return $content !== '' ? $content : $this->title;
    }
}

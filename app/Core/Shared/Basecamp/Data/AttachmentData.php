<?php

namespace App\Core\Shared\Basecamp\Data;

final readonly class AttachmentData
{
    public function __construct(
        public string $id,
        public string $contentType,
        public int $byteSize,
        public ?int $width,
        public ?int $height,
        public string $filename,
        public ?string $downloadUrl,
        public ?string $previewUrl,
        public ?string $thumbnailUrl,
        public bool $previewable,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            contentType: BasecampPayload::string($payload, 'content_type'),
            byteSize: BasecampPayload::int($payload, 'byte_size'),
            width: BasecampPayload::nullableInt($payload, 'width'),
            height: BasecampPayload::nullableInt($payload, 'height'),
            filename: BasecampPayload::string($payload, 'filename'),
            downloadUrl: BasecampPayload::nullableString($payload, 'download_url'),
            previewUrl: BasecampPayload::nullableString($payload, 'preview_url'),
            thumbnailUrl: BasecampPayload::nullableString($payload, 'thumbnail_url'),
            previewable: BasecampPayload::bool($payload, 'previewable'),
        );
    }

    public function isImage(): bool
    {
        return str_starts_with($this->contentType, 'image/');
    }
}

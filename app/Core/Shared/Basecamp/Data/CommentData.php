<?php

namespace App\Core\Shared\Basecamp\Data;

use Carbon\CarbonImmutable;

final readonly class CommentData
{
    /** @param list<AttachmentData> $attachments */
    public function __construct(
        public string $id,
        public CarbonImmutable $createdAt,
        public string $parentId,
        public array $attachments,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            createdAt: BasecampPayload::carbon($payload, 'created_at'),
            parentId: BasecampPayload::string(BasecampPayload::object($payload, 'parent'), 'id'),
            attachments: array_map(
                fn (array $attachment): AttachmentData => AttachmentData::fromPayload($attachment),
                BasecampPayload::list($payload, 'content_attachments'),
            ),
        );
    }

    /** @return list<AttachmentData> */
    public function imageAttachments(): array
    {
        return array_values(array_filter(
            $this->attachments,
            fn (AttachmentData $attachment): bool => $attachment->isImage(),
        ));
    }
}

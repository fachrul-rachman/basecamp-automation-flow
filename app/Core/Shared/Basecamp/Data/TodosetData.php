<?php

namespace App\Core\Shared\Basecamp\Data;

final readonly class TodosetData
{
    /** @param list<TodosetTodolistData> $todolists */
    public function __construct(
        public string $id,
        public string $bucketId,
        public array $todolists,
        public string $todolistsUrl,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: BasecampPayload::string($payload, 'id'),
            bucketId: BasecampPayload::string(BasecampPayload::object($payload, 'bucket'), 'id'),
            todolists: array_map(
                fn (array $todolist): TodosetTodolistData => TodosetTodolistData::fromPayload($todolist),
                BasecampPayload::list($payload, 'todolists'),
            ),
            todolistsUrl: BasecampPayload::string($payload, 'todolists_url'),
        );
    }
}

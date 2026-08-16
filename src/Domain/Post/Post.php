<?php declare(strict_types=1);

namespace Domain\Post;

use JsonSerializable;

final readonly class Post implements JsonSerializable
{

    public function __construct(
        private int $id,
        private int $userId,
        private string $title,
        private string $body
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['id'],
            (int)$data['userId'],
            $data['title'],
            $data['body']
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}

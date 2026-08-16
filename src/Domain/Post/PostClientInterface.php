<?php declare(strict_types=1);

namespace Domain\Post;

interface PostClientInterface
{

    /**
     * @return Post[]
     */
    public function getAll(): array;

    public function getById(int $id): Post;
}

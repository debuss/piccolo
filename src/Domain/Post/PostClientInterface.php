<?php declare(strict_types=1);

namespace Domain\Post;

use Domain\Shared\Exception\NotFoundException;

interface PostClientInterface
{

    /**
     * @return Post[]
     */
    public function getAll(): array;

    /**
     * @throws NotFoundException When no post exists with this id
     */
    public function getById(int $id): Post;
}

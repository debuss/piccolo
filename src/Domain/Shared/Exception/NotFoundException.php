<?php declare(strict_types=1);

namespace Domain\Shared\Exception;

use RuntimeException;

use function sprintf;

class NotFoundException extends RuntimeException
{

    public static function create(string $resource, int|string $id): self
    {
        return new self(sprintf('%s "%s" could not be found', $resource, $id));
    }
}

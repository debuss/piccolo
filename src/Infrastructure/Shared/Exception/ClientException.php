<?php declare(strict_types=1);

namespace Infrastructure\Shared\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown by Infrastructure HTTP clients when an upstream call fails (network error, non-2xx response, ...).
 */
class ClientException extends RuntimeException
{

    public static function create(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}

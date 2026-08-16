<?php declare(strict_types=1);

namespace Domain\Shared\Exception;

use Mezzio\ProblemDetails\Exception\{CommonProblemDetailsExceptionTrait, ProblemDetailsExceptionInterface};
use RuntimeException;
use Throwable;

class ClientException extends RuntimeException implements ProblemDetailsExceptionInterface
{

    use CommonProblemDetailsExceptionTrait;

    public static function create(string $message, int $status = 500, array $additional = [], ?Throwable $previous = null): self
    {
        $exception = new self($message, $status, $previous);

        $exception->status = $status;
        $exception->type = 'https://developer.mozilla.org/fr/docs/Web/HTTP/Reference/Status/' . $status;
        $exception->title = 'An error occurred while processing the request.';
        $exception->detail = $message;
        $exception->additional = $additional;

        return $exception;
    }
}

<?php declare(strict_types=1);

namespace Domain\Shared\Exception;

use Mezzio\ProblemDetails\Exception\{CommonProblemDetailsExceptionTrait, ProblemDetailsExceptionInterface};
use RuntimeException;

class NotFoundException extends RuntimeException implements ProblemDetailsExceptionInterface
{

    use CommonProblemDetailsExceptionTrait;

    public static function create(int $id, array $additional = []): self
    {
        $exception = new self(sprintf('The requested ID "%s" could not be found', $id), 404);

        $exception->status = 404;
        $exception->type = 'https://developer.mozilla.org/fr/docs/Web/HTTP/Reference/Status/404';
        $exception->title = 'The requested ID does not exist.';
        $exception->detail = sprintf('The requested ID "%s" does not exist.', $id);
        $exception->additional = $additional;

        return $exception;
    }
}

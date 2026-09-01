<?php declare(strict_types=1);

namespace Application\Http\ProblemDetails;

use Domain\Shared\Exception\NotFoundException;
use Infrastructure\Shared\Exception\ClientException;
use Throwable;

/**
 * Maps plain Domain/Infrastructure exceptions to an HTTP status and title, so that
 * ProblemDetailsResponseFactory can serialize them as RFC 7807 responses without those exceptions having to know
 * anything about HTTP.
 *
 * @see MappingProblemDetailsResponseFactory
 */
final readonly class ExceptionStatusMapper
{

    /**
     * @param array<class-string<Throwable>, array{status: int, title: string}> $map
     */
    public function __construct(
        private array $map = [
            NotFoundException::class => ['status' => 404, 'title' => 'Not Found'],
            ClientException::class => ['status' => 502, 'title' => 'Bad Gateway'],
        ]
    ) {}

    /**
     * @return array{status: int, title: string}|null
     */
    public function map(Throwable $e): ?array
    {
        foreach ($this->map as $class => $mapping) {
            if ($e instanceof $class) {
                return $mapping;
            }
        }

        return null;
    }
}

<?php declare(strict_types=1);

namespace Application\Http\ProblemDetails;

use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Throwable;

/**
 * Extends the stock ProblemDetailsResponseFactory to also handle plain Throwables (Domain/Infrastructure
 * exceptions that do not implement ProblemDetailsExceptionInterface) via ExceptionStatusMapper.
 *
 * Exceptions that DO implement ProblemDetailsExceptionInterface still go through the parent's native handling.
 * Anything unmapped falls back to the parent's default (500 Internal Server Error).
 */
class MappingProblemDetailsResponseFactory extends ProblemDetailsResponseFactory
{

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private readonly ExceptionStatusMapper $mapper
    ) {
        parent::__construct($responseFactory);
    }

    public function createResponseFromThrowable(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        $mapping = $this->mapper->map($e);
        if ($mapping === null) {
            return parent::createResponseFromThrowable($request, $e);
        }

        return $this->createResponse($request, $mapping['status'], $e->getMessage(), $mapping['title']);
    }
}

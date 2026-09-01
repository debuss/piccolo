<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A no-op PSR-15 request handler: returns an empty JSON response. Used where a test only needs a valid
 * RequestHandlerInterface instance and does not care what it does.
 */
final class NoopRequestHandler implements RequestHandlerInterface
{

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([]);
    }
}

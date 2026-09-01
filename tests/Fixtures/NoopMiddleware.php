<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * A no-op PSR-15 middleware: delegates straight to the next handler. Used where a test only needs a valid
 * MiddlewareInterface instance and does not care what it does.
 */
final class NoopMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

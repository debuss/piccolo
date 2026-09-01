<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Appends its marker to the request's "trail" attribute, then delegates to the next handler. Used to assert
 * middleware execution order/scoping without depending on any real business logic.
 */
final readonly class RecordingMiddleware implements MiddlewareInterface
{

    public function __construct(
        private string $marker
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trail = $request->getAttribute('trail', []);
        $trail[] = $this->marker;

        return $handler->handle($request->withAttribute('trail', $trail));
    }
}

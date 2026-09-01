<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * Ends the pipeline: returns a JSON response exposing the request's "trail" attribute and this middleware's
 * "source" marker, without delegating further. Used to observe which middleware actually handled a request.
 */
final readonly class TerminalMiddleware implements MiddlewareInterface
{

    public function __construct(
        private string $source
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse([
            'trail' => $request->getAttribute('trail', []),
            'source' => $this->source,
        ]);
    }
}

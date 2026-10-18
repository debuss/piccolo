<?php declare(strict_types=1);

namespace Application\Middleware;

use Awareness\{StreamFactoryAwareInterface, StreamFactoryAwareTrait};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * HttpCacheMiddleware
 *
 * Lets clients cache the successful GET responses of a route for an hour: adds a `Cache-Control` header and an `ETag`
 * computed from the body, and answers `304 Not Modified` without body when the `If-None-Match` header of the request
 * matches it, so that the content is not downloaded again.
 *
 * Meant for the routes serving content that rarely changes, added on the handler with the `#[Middleware]` attribute:
 *
 *  #[Get('/openapi', name: 'api.v1.openapi')]
 *  #[Middleware(HttpCacheMiddleware::class)]
 *  public function handle(ServerRequestInterface $request): ResponseInterface
 *
 * The stream factory, used to empty the body of `304` responses, is injected after resolution by the container.
 */
class HttpCacheMiddleware implements MiddlewareInterface, StreamFactoryAwareInterface
{

    use StreamFactoryAwareTrait;

    private const int MAX_AGE = 3600;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true) || $response->getStatusCode() !== 200) {
            return $response;
        }

        $etag = sprintf('"%s"', hash('xxh128', (string)$response->getBody()));

        $response = $response
            ->withHeader('Cache-Control', sprintf('public, max-age=%d', self::MAX_AGE))
            ->withHeader('ETag', $etag);

        if (!$this->matches($request->getHeaderLine('If-None-Match'), $etag)) {
            return $response;
        }

        return $response
            ->withStatus(304)
            ->withoutHeader('Content-Length')
            ->withBody($this->streamFactory->createStream());
    }

    /**
     * `If-None-Match` holds `*` or a list of entity tags, compared weakly: `W/"abc"` matches `"abc"` (RFC 9110, 13.1.2).
     */
    private function matches(string $ifNoneMatch, string $etag): bool
    {
        if (trim($ifNoneMatch) === '*') {
            return true;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }
}

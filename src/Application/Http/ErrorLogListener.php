<?php declare(strict_types=1);

namespace Application\Http;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Error Log Listener
 *
 * Logs the errors caught by the ErrorHandler and ProblemDetailsMiddleware listeners (both share this signature).
 *
 * Only non-sensitive request data is logged: headers, cookies and body can contain credentials (Authorization header,
 * session cookie, password field, ...) and logs are often shipped to third-party services.
 * Route attributes are not available here: both middlewares are piped before the routing, and PSR-7 requests are
 * immutable, so they only see the request as it entered them.
 * The URI comes from the `originalUri` attribute (OriginalMessages middleware) when available, as middleware piped on
 * a path (e.g. `/api`) see the URI without that path prefix.
 */
readonly class ErrorLogListener
{

    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke(Throwable $error, ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->logger->error($error->getMessage(), [
            'exception' => $error,
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string)$request->getAttribute('originalUri', $request->getUri()),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
            ],
        ]);
    }
}

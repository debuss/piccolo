<?php declare(strict_types=1);

namespace Application\Middleware;

use Application\Http\RequestIdProcessor;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * RequestIdMiddleware
 *
 * Identifies each request, to find all its logs and relate them to the logs of a reverse proxy or of the client:
 * - the `X-Request-Id` header sent by a reverse proxy or a load balancer is kept, if it is a valid identifier (letters,
 *   digits, `.`, `_`, `:` and `-`, at most 128 characters), otherwise a random one is generated;
 * - the identifier is added to every log record (see RequestIdProcessor), set in the `request_id` attribute of the
 *   request, and sent back in the `X-Request-Id` header of the response.
 *
 * It is piped first, before the ErrorHandler, so that the errors are logged with the identifier and their responses
 * have the header too.
 */
readonly class RequestIdMiddleware implements MiddlewareInterface
{

    public const string HEADER = 'X-Request-Id';
    public const string ATTRIBUTE = 'request_id';

    public function __construct(
        private RequestIdProcessor $processor
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine(self::HEADER);

        // The header comes from the client when there is no reverse proxy setting it, so it is never trusted as is: an
        // invalid one (too long, or with spaces or line breaks that would forge log lines) is replaced.
        if (!preg_match('/^[\w.:-]{1,128}$/', $requestId)) {
            $requestId = bin2hex(random_bytes(16));
        }

        $this->processor->setRequestId($requestId);

        try {
            $response = $handler->handle($request->withAttribute(self::ATTRIBUTE, $requestId));
        } finally {
            // A long-running process (worker mode) must not log the identifier with the next request
            $this->processor->setRequestId(null);
        }

        return $response->withHeader(self::HEADER, $requestId);
    }
}

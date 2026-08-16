<?php

namespace Application\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * ApiAcceptHeaderMiddleware
 *
 * This middleware forces the `Accept` header to `application/json` for API routes.
 *
 * Not a necessary middleware, but it will allow to display the Problem Details response from the
 * ProblemDetailsMiddleware when testing on a web browser, which usually sends `text/html` as the default `Accept`
 * header.
 */
class ApiAcceptHeaderMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withHeader('Accept', 'application/json');

        return $handler->handle($request);
    }
}

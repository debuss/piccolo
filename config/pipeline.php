<?php declare(strict_types=1);

use Application\Application;
use Application\Middleware\ApiAcceptHeaderMiddleware;
use Laminas\Stratigility\Handler\NotFoundHandler;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Router\Middleware\{DispatchMiddleware,
    ImplicitHeadMiddleware,
    ImplicitOptionsMiddleware,
    MethodNotAllowedMiddleware,
    RouteMiddleware};
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\ProblemDetails\ProblemDetailsMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Middleware pipeline configuration
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/router/piping/
 */
return static function (Application $app, ContainerInterface $container): void
{
    // The error handler should be the first (most outer) middleware to catch all exceptions.
    $app->pipe(ErrorHandler::class);

    // Pipe more middleware here that you want to execute on every request:
    // - bootstrapping
    // - pre-conditions
    // - modifications to outgoing responses
    //
    // Piped Middleware may be either callables or service names. Middleware may
    // also be passed as an array; each item in the array must resolve to
    // middleware eventually (i.e., callable or service name).
    //
    // Middleware can be attached to specific paths, allowing you to mix and match
    // applications under a common domain.  The handlers in each middleware
    // attached this way will see a URI with the matched path segment removed.
    //
    // i.e., path of "/api/member/profile" only passes "/member/profile" to $apiMiddleware
    // - $app->pipe('/api', $apiMiddleware);
    // - $app->pipe('/docs', $apiDocMiddleware);
    // - $app->pipe('/files', $filesMiddleware);
    $app->pipe('/api', [
        ApiAcceptHeaderMiddleware::class,
        ProblemDetailsMiddleware::class,
        BodyParamsMiddleware::class
    ]);

    // Register the routing middleware in the middleware pipeline.
    // This middleware registers the Mezzio\Router\RouteResult request attribute.
    $app->pipe(RouteMiddleware::class);

    // The following handle routing failures for common conditions:
    // - HEAD request but no routes answer that method
    // - OPTIONS request but no routes answer that method
    // - method not allowed
    // Order here matters; the MethodNotAllowedMiddleware should be placed after the Implicit*Middleware.
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);

    // Add more middleware here that needs to introspect the routing results; this might include:
    // - route-based authentication
    // - route-based validation
    // - etc.

    // Register the dispatch middleware in the middleware pipeline.
    // This middleware uses the Mezzio\Router\RouteResult from the RouteMiddleware to generate a response if a route
    // was matched, otherwise it will go to the NotFoundHandler which will generate a 404 Not Found response.
    $app->pipe(DispatchMiddleware::class);

    // At this point, if no Response is returned by any middleware, the NotFoundHandler kicks in; alternately, you can
    // provide your own "Not Found" handler and replace it below.
    // This default one is provided by Laminas Stratigility.
    $app->pipe(NotFoundHandler::class);
};

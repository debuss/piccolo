<?php declare(strict_types=1);

use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Cors\Configuration\{ConfigurationInterface, ProjectConfiguration};
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Router\{FastRouteRouter, RouteCollector};
use Mezzio\Router\Middleware\RouteMiddleware;
use Piccolo\Application;
use Piccolo\Container\ContainerBuilder;
use Piccolo\Middleware\{LazyLoadingMiddleware, RequestIdMiddleware, SecurityHeadersMiddleware};
use Piccolo\Runtime\RunnerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The middlewares piped by config/pipeline.php, as the container entries they are lazily resolved from.
 *
 * @return string[]
 */
function pipedMiddlewares(ProjectConfiguration $corsConfiguration): array
{
    $pipeline = new MiddlewarePipe();
    $container = new ContainerBuilder()->instance(ConfigurationInterface::class, $corsConfiguration)->build();
    $runner = new class implements RunnerInterface {
        public function run(RequestHandlerInterface $handler): void {}
    };

    $app = new Application($pipeline, $runner, new RouteCollector(new FastRouteRouter()), $container);

    (require config_path('pipeline.php'))($app, $container);

    return array_map(
        fn($middleware) => $middleware instanceof LazyLoadingMiddleware
            ? $middleware->getMiddleware()
            : $middleware::class,
        iterator_to_array($pipeline, false)
    );
}

test('pipes the RequestIdMiddleware first, so that it wraps the error handler', function () {
    expect(pipedMiddlewares(new ProjectConfiguration([]))[0])->toBe(RequestIdMiddleware::class);
});

test('pipes the SecurityHeadersMiddleware right after, so that the error responses have the headers too', function () {
    $middlewares = pipedMiddlewares(new ProjectConfiguration([]));

    expect($middlewares[1])->toBe(SecurityHeadersMiddleware::class)
        ->and($middlewares[2])->toBe(Laminas\Stratigility\Middleware\ErrorHandler::class);
});

test('does not pipe the CORS middleware when no origin is allowed', function () {
    expect(pipedMiddlewares(new ProjectConfiguration([])))->not->toContain(CorsMiddleware::class);
});

test('pipes the CORS middleware before the routing when an origin is allowed', function () {
    $middlewares = pipedMiddlewares(new ProjectConfiguration(['allowed_origins' => ['https://app.example.com']]));

    expect($middlewares)->toContain(CorsMiddleware::class)
        ->and(array_search(CorsMiddleware::class, $middlewares))
        ->toBeLessThan(array_search(RouteMiddleware::class, $middlewares));
});

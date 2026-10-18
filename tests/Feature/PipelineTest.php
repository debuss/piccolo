<?php declare(strict_types=1);

use Application\Application;
use Application\Middleware\LazyLoadingMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Cors\Configuration\{ConfigurationInterface, ProjectConfiguration};
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouteCollector;
use Tests\Fixtures\{FakeContainer, FakeRequestHandlerRunner, FakeRouter};

/**
 * The middlewares piped by config/pipeline.php, as the container entries they are lazily resolved from.
 *
 * @return string[]
 */
function pipedMiddlewares(ProjectConfiguration $corsConfiguration): array
{
    $pipeline = new MiddlewarePipe();
    $container = new FakeContainer([ConfigurationInterface::class => $corsConfiguration]);
    $app = new Application($pipeline, new FakeRequestHandlerRunner(), new RouteCollector(new FakeRouter()), $container);

    (require config_path('pipeline.php'))($app, $container);

    return array_map(
        fn($middleware) => $middleware instanceof LazyLoadingMiddleware
            ? $middleware->getMiddleware()
            : $middleware::class,
        iterator_to_array($pipeline, false)
    );
}

test('does not pipe the CORS middleware when no origin is allowed', function () {
    expect(pipedMiddlewares(new ProjectConfiguration([])))->not->toContain(CorsMiddleware::class);
});

test('pipes the CORS middleware before the routing when an origin is allowed', function () {
    $middlewares = pipedMiddlewares(new ProjectConfiguration(['allowed_origins' => ['https://app.example.com']]));

    expect($middlewares)->toContain(CorsMiddleware::class)
        ->and(array_search(CorsMiddleware::class, $middlewares))
        ->toBeLessThan(array_search(RouteMiddleware::class, $middlewares));
});

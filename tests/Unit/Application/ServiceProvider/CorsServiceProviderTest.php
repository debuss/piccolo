<?php declare(strict_types=1);

use Application\ServiceProvider\CorsServiceProvider;
use Borsch\Config\Config;
use Laminas\Diactoros\{ResponseFactory, ServerRequest, ServerRequestFactory, UriFactory};
use League\Container\Container;
use Mezzio\Cors\Configuration\ConfigurationInterface;
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Router\{FastRouteRouter, Route, RouterInterface};
use Psr\Http\Message\{ResponseFactoryInterface, ServerRequestFactoryInterface, UriFactoryInterface};
use Tests\Fixtures\{NoopMiddleware, NoopRequestHandler};

/**
 * A container with the CORS service provider, the given configuration, and a router holding a single
 * `GET /api/v1/posts` route.
 */
function makeCorsContainer(array $config): Container
{
    $router = new FastRouteRouter();
    $router->addRoute(new Route('/api/v1/posts', new NoopMiddleware(), ['GET'], 'api.v1.posts'));

    $container = new Container();
    $container->add(Config::class, new Config($config));
    $container->add(UriFactoryInterface::class, new UriFactory());
    $container->add(ServerRequestFactoryInterface::class, new ServerRequestFactory());
    $container->add(ResponseFactoryInterface::class, new ResponseFactory());
    $container->add(RouterInterface::class, $router);
    $container->addServiceProvider(new CorsServiceProvider());

    return $container;
}

function corsRequest(string $method, string $origin, array $headers = []): ServerRequest
{
    return new ServerRequest(
        uri: 'http://api.test/api/v1/posts',
        method: $method,
        headers: ['Origin' => $origin, ...$headers]
    );
}

// --- Configuration ---------------------------------------------------------------------------------------------

test('allows no origin by default, CORS being disabled', function () {
    $configuration = makeCorsContainer([])->get(ConfigurationInterface::class);

    expect($configuration->allowedOrigins())->toBe([])
        ->and($configuration->allowedHeaders())->toBe(['Content-Type'])
        ->and($configuration->exposedHeaders())->toBe([])
        ->and($configuration->credentialsAllowed())->toBeFalse()
        ->and($configuration->allowedMaxAge())->toBe(ConfigurationInterface::PREFLIGHT_CACHE_DISABLED);
});

test('reads the comma-separated lists, trimmed and without empty items', function () {
    $configuration = makeCorsContainer([
        'CORS_ALLOWED_ORIGINS' => ' https://app.example.com, ,http://localhost:5173 ',
        'CORS_ALLOWED_HEADERS' => 'Content-Type, Authorization',
        'CORS_EXPOSED_HEADERS' => 'ETag',
        'CORS_ALLOW_CREDENTIALS' => 'true',
        'CORS_MAX_AGE' => '600',
    ])->get(ConfigurationInterface::class);

    expect($configuration->allowedOrigins())->toBe(['https://app.example.com', 'http://localhost:5173'])
        ->and($configuration->allowedHeaders())->toBe(['Content-Type', 'Authorization'])
        ->and($configuration->exposedHeaders())->toBe(['ETag'])
        ->and($configuration->credentialsAllowed())->toBeTrue()
        ->and($configuration->allowedMaxAge())->toBe('600');
});

test('refuses credentials when any origin is allowed', function () {
    $container = makeCorsContainer(['CORS_ALLOWED_ORIGINS' => '*', 'CORS_ALLOW_CREDENTIALS' => 'true']);

    expect(fn() => $container->get(ConfigurationInterface::class))
        ->toThrow(InvalidArgumentException::class, 'CORS_ALLOW_CREDENTIALS');
});

// --- Middleware ------------------------------------------------------------------------------------------------

test('answers the preflight request of an allowed origin with the methods of the requested route', function () {
    $middleware = makeCorsContainer(['CORS_ALLOWED_ORIGINS' => 'https://*.example.com'])->get(CorsMiddleware::class);

    $response = $middleware->process(
        corsRequest('OPTIONS', 'https://app.example.com', ['Access-Control-Request-Method' => 'GET']),
        new NoopRequestHandler()
    );

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('https://app.example.com')
        ->and($response->getHeaderLine('Access-Control-Allow-Methods'))->toBe('GET')
        ->and($response->getHeaderLine('Access-Control-Allow-Headers'))->toBe('Content-Type');
});

test('adds the CORS headers to the response of an allowed origin', function () {
    $middleware = makeCorsContainer(['CORS_ALLOWED_ORIGINS' => 'https://app.example.com'])->get(CorsMiddleware::class);

    $response = $middleware->process(corsRequest('GET', 'https://app.example.com'), new NoopRequestHandler());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('https://app.example.com')
        ->and($response->getHeaderLine('Vary'))->toBe('Origin');
});

test('rejects the requests of the other origins with a 403', function () {
    $middleware = makeCorsContainer(['CORS_ALLOWED_ORIGINS' => 'https://app.example.com'])->get(CorsMiddleware::class);

    $response = $middleware->process(corsRequest('GET', 'https://evil.test'), new NoopRequestHandler());

    expect($response->getStatusCode())->toBe(403)
        ->and($response->hasHeader('Access-Control-Allow-Origin'))->toBeFalse();
});

test('lets the requests without origin through', function () {
    $middleware = makeCorsContainer(['CORS_ALLOWED_ORIGINS' => 'https://app.example.com'])->get(CorsMiddleware::class);

    $response = $middleware->process(
        new ServerRequest(uri: 'http://api.test/api/v1/posts', method: 'GET'),
        new NoopRequestHandler()
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->hasHeader('Access-Control-Allow-Origin'))->toBeFalse();
});

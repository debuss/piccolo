<?php declare(strict_types=1);

use Application\Application;
use Laminas\Diactoros\ServerRequest;
use Laminas\Stratigility\Middleware\{CallableMiddlewareDecorator, RequestHandlerMiddleware};
use Laminas\Stratigility\MiddlewarePipe;
use Application\Middleware\LazyLoadingMiddleware;
use Mezzio\Router\{Route, RouteCollector};
use Tests\Fixtures\{FakeContainer,
    FakeRequestHandlerRunner,
    FakeRouter,
    NoopMiddleware,
    NoopRequestHandler,
    RecordingMiddleware,
    TerminalMiddleware};

/**
 * Builds a fresh Application with real MiddlewarePipe/RouteCollector but fake container/router/runner, so tests
 * can inspect exactly what pipe()/route() do without booting the real League Container.
 *
 * @return array{app: Application, pipeline: MiddlewarePipe, router: FakeRouter, container: FakeContainer, runner: FakeRequestHandlerRunner}
 */
function makeApplication(): array
{
    $pipeline = new MiddlewarePipe();
    $router = new FakeRouter();
    $container = new FakeContainer();
    $runner = new FakeRequestHandlerRunner();

    $app = new Application(
        $pipeline,
        $runner,
        new RouteCollector($router),
        $container
    );

    return compact('app', 'pipeline', 'router', 'container', 'runner');
}

// --- pipe(): normalizing the different middleware shapes ------------------------------------------------------

test('pipe() wraps a class-string middleware for lazy resolution from the container', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    $app->pipe(NoopMiddleware::class);

    $entries = iterator_to_array($pipeline);
    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(LazyLoadingMiddleware::class);
});

test('pipe() wraps a callable middleware', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    $app->pipe(fn($request, $handler) => $handler->handle($request));

    $entries = iterator_to_array($pipeline);
    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(CallableMiddlewareDecorator::class);
});

test('pipe() wraps a request handler instance', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    $app->pipe(new NoopRequestHandler());

    $entries = iterator_to_array($pipeline);
    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(RequestHandlerMiddleware::class);
});

test('pipe() throws for a middleware string that is not a real class', function () {
    ['app' => $app] = makeApplication();

    expect(fn() => $app->pipe('Totally\\Not\\A\\Real\\Class'))
        ->toThrow(InvalidArgumentException::class);
});

// --- pipe(): array handling -------------------------------------------------------------------------------------

test('pipe() with an array and no path pipes each middleware individually', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    // Regression test: this used to throw a TypeError because the recursive call passed the array itself
    // (rather than the resolved path) back into pipe().
    $app->pipe([new NoopMiddleware(), new NoopMiddleware(), new NoopMiddleware()]);

    expect(iterator_to_array($pipeline))->toHaveCount(3);
});

test('pipe() with a path and an array groups the middleware into a single path-scoped entry', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    $app->pipe('/api', [new NoopMiddleware(), new NoopMiddleware(), new NoopMiddleware()]);

    // All three middleware must be matched by a single path check, not three.
    expect(iterator_to_array($pipeline))->toHaveCount(1);
});

// --- pipe(): actual request dispatch, proving order + path-scoping behave correctly ------------------------------

test('piped middleware execute in registration order, and a path-scoped group only runs for matching requests', function () {
    ['app' => $app, 'pipeline' => $pipeline] = makeApplication();

    $app->pipe(new RecordingMiddleware('outer'));
    $app->pipe('/api', [new RecordingMiddleware('api-1'), new TerminalMiddleware('api')]);
    $app->pipe(new TerminalMiddleware('fallback'));

    $matching = json_decode(
        (string) $pipeline->handle(new ServerRequest(uri: '/api/posts', method: 'GET'))->getBody(),
        true
    );

    expect($matching)->toBe(['trail' => ['outer', 'api-1'], 'source' => 'api']);

    $nonMatching = json_decode(
        (string) $pipeline->handle(new ServerRequest(uri: '/other', method: 'GET'))->getBody(),
        true
    );

    expect($nonMatching)->toBe(['trail' => ['outer'], 'source' => 'fallback']);
});

test('a class-string middleware is lazily resolved from the container only when a request is actually processed', function () {
    ['app' => $app, 'pipeline' => $pipeline, 'container' => $container] = makeApplication();

    $container->set(RecordingMiddleware::class, new RecordingMiddleware('lazy'));

    $app->pipe(RecordingMiddleware::class);
    $app->pipe(new TerminalMiddleware('end'));

    $response = json_decode(
        (string) $pipeline->handle(new ServerRequest(uri: '/', method: 'GET'))->getBody(),
        true
    );

    expect($response)->toBe(['trail' => ['lazy'], 'source' => 'end']);
});

// --- route()/get()/post()/put()/patch()/delete()/any() ------------------------------------------------------------

test('get(), post(), put(), patch(), delete() register a route with the matching single HTTP method', function (string $helper, string $expectedMethod) {
    ['app' => $app] = makeApplication();

    $app->{$helper}('/posts', new NoopMiddleware(), 'posts.' . $helper);

    $routes = $app->getRoutes();
    expect($routes)->toHaveCount(1);

    /** @var Route $route */
    $route = $routes[0];
    expect($route->getPath())->toBe('/posts')
        ->and($route->getAllowedMethods())->toBe([$expectedMethod])
        ->and($route->getName())->toBe('posts.' . $helper);
})->with([
    ['get', 'GET'],
    ['post', 'POST'],
    ['put', 'PUT'],
    ['patch', 'PATCH'],
    ['delete', 'DELETE'],
]);

test('any() registers a route with no method restriction', function () {
    ['app' => $app] = makeApplication();

    $app->any('/posts', new NoopMiddleware(), 'posts.any');

    $route = $app->getRoutes()[0];
    expect($route->getAllowedMethods())->toBeNull();
});

test('route() registers a route with multiple explicit HTTP methods', function () {
    ['app' => $app] = makeApplication();

    $app->route('/posts', new NoopMiddleware(), ['GET', 'POST'], 'posts.mixed');

    $route = $app->getRoutes()[0];
    expect($route->getAllowedMethods())->toBe(['GET', 'POST']);
});

test('getRoutes() reflects every route registered so far', function () {
    ['app' => $app] = makeApplication();

    $app->get('/posts', new NoopMiddleware(), 'posts.index');
    $app->post('/posts', new NoopMiddleware(), 'posts.create');

    expect($app->getRoutes())->toHaveCount(2);
});

// --- run() ---------------------------------------------------------------------------------------------------

test('run() delegates to the composed request handler runner', function () {
    ['app' => $app, 'runner' => $runner] = makeApplication();

    expect($runner->wasRun)->toBeFalse();

    $app->run();

    expect($runner->wasRun)->toBeTrue();
});

<?php declare(strict_types=1);

use Application\Middleware\HttpCacheMiddleware;
use Laminas\Diactoros\{Response, ServerRequest, StreamFactory};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The stream factory is set manually, as the container does after resolution through the Awareness pattern.
 */
function makeHttpCacheMiddleware(): HttpCacheMiddleware
{
    $middleware = new HttpCacheMiddleware();
    $middleware->setStreamFactory(new StreamFactory());

    return $middleware;
}

/**
 * A request handler returning a response with the given status and body.
 */
function respondWith(string $body, int $status = 200): RequestHandlerInterface
{
    return new class ($body, $status) implements RequestHandlerInterface {
        public function __construct(private string $body, private int $status) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $response = new Response(status: $this->status);
            $response->getBody()->write($this->body);

            return $response->withHeader('Content-Length', (string)strlen($this->body));
        }
    };
}

function etagOf(string $body): string
{
    return sprintf('"%s"', hash('xxh128', $body));
}

test('adds the cache headers to a successful GET response, and keeps its body', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest(method: 'GET'), respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Cache-Control'))->toBe('public, max-age=3600')
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('openapi: 3.1.0'))
        ->and((string)$response->getBody())->toBe('openapi: 3.1.0');
});

test('answers 304 without body when If-None-Match matches the ETag', function (string $ifNoneMatch) {
    $request = new ServerRequest(method: 'GET', headers: ['If-None-Match' => $ifNoneMatch]);

    $response = makeHttpCacheMiddleware()->process($request, respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(304)
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('openapi: 3.1.0'))
        ->and($response->getHeaderLine('Cache-Control'))->toBe('public, max-age=3600')
        ->and($response->hasHeader('Content-Length'))->toBeFalse()
        ->and((string)$response->getBody())->toBe('');
})->with([
    'the same ETag' => [etagOf('openapi: 3.1.0')],
    'a weak ETag' => ['W/' . etagOf('openapi: 3.1.0')],
    'one ETag of a list' => ['"outdated", ' . etagOf('openapi: 3.1.0')],
    'any ETag' => ['*'],
]);

test('answers 200 with the body when If-None-Match does not match the ETag', function () {
    $request = new ServerRequest(method: 'GET', headers: ['If-None-Match' => etagOf('openapi: 3.0.0')]);

    $response = makeHttpCacheMiddleware()->process($request, respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(200)
        ->and((string)$response->getBody())->toBe('openapi: 3.1.0');
});

test('leaves the responses of other HTTP methods untouched', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest(method: 'POST'), respondWith('created', 201));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->hasHeader('ETag'))->toBeFalse()
        ->and($response->hasHeader('Cache-Control'))->toBeFalse();
});

test('leaves unsuccessful responses untouched', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest(method: 'GET'), respondWith('Not Found', 404));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->hasHeader('ETag'))->toBeFalse()
        ->and($response->hasHeader('Cache-Control'))->toBeFalse();
});

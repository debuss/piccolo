<?php declare(strict_types=1);

use Laminas\Diactoros\ServerRequest;

/*
 * End-to-end tests: the requests go through the real container, middleware pipeline and routes of the application.
 */

test('renders the home page', function () {
    $response = httpApplication()->handle(new ServerRequest(uri: '/', method: 'GET'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/html; charset=utf-8')
        ->and($response->getHeaderLine('X-Request-Id'))->toMatch('/^[0-9a-f]{32}$/');
});

test('answers the ping of the manual route', function () {
    $response = httpApplication()->handle(new ServerRequest(uri: '/api/ping', method: 'GET'));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode((string)$response->getBody(), true))->toHaveKey('ack');
});

test('serves the OpenAPI document with the HTTP cache middleware of its attribute', function () {
    $response = httpApplication()->handle(new ServerRequest(uri: '/api/v1/openapi', method: 'GET'));
    $etag = $response->getHeaderLine('ETag');

    $cached = httpApplication()->handle(
        new ServerRequest(uri: '/api/v1/openapi', method: 'GET', headers: ['If-None-Match' => $etag])
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/yaml')
        ->and($etag)->not->toBe('')
        ->and($cached->getStatusCode())->toBe(304);
});

test('renders the Redoc page, with the URI of the OpenAPI document', function () {
    $response = httpApplication()->handle(new ServerRequest(uri: '/api/v1/redoc', method: 'GET'));

    expect($response->getStatusCode())->toBe(200)
        ->and((string)$response->getBody())->toContain('/api/v1/openapi');
});

test('answers 404 for an unknown route, 405 for a method not allowed', function () {
    expect(httpApplication()->handle(new ServerRequest(uri: '/missing', method: 'GET'))->getStatusCode())->toBe(404)
        ->and(httpApplication()->handle(new ServerRequest(uri: '/api/ping', method: 'POST'))->getStatusCode())->toBe(405);
});

test('answers the implicit HEAD and OPTIONS requests', function () {
    expect(httpApplication()->handle(new ServerRequest(uri: '/api/ping', method: 'HEAD'))->getStatusCode())->toBe(200)
        ->and(httpApplication()->handle(new ServerRequest(uri: '/api/ping', method: 'OPTIONS'))->getStatusCode())->toBe(200);
});

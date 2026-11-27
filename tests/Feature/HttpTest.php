<?php declare(strict_types=1);

/*
 * End-to-end tests: the requests go through the real container, middleware pipeline and routes of the application.
 */

test('renders the home page', function () {
    client()->get('/')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertHeader('X-Request-Id');

    expect(client()->get('/')->header('X-Request-Id'))->toMatch('/^[0-9a-f]{32}$/');
});

test('answers the ping of the manual route', function () {
    expect(client()->get('/api/ping')->assertOk()->json())->toHaveKey('ack');
});

test('serves the OpenAPI document with the HTTP cache middleware of its attribute', function () {
    $client = client();
    $etag = $client->get('/api/v1/openapi')->assertOk()->assertHeader('Content-Type', 'text/yaml')->header('ETag');

    expect($etag)->not->toBe('');

    $client->get('/api/v1/openapi', ['If-None-Match' => $etag])->assertStatus(304);
});

test('renders the Redoc page, with the URI of the OpenAPI document', function () {
    client()->get('/api/v1/redoc')->assertOk()->assertBodyContains('/api/v1/openapi');
});

test('answers 404 for an unknown route, 405 for a method not allowed', function () {
    client()->get('/missing')->assertStatus(404);
    client()->post('/api/ping')->assertStatus(405);
});

test('answers the implicit HEAD and OPTIONS requests', function () {
    client()->head('/api/ping')->assertOk();
    client()->options('/api/ping')->assertOk();
});

test('adds the security headers to the responses, the error ones too', function (string $uri) {
    client()->get($uri)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        // Production only
        ->assertHeaderMissing('Strict-Transport-Security');
})->with(['/', '/api/ping', '/missing']);

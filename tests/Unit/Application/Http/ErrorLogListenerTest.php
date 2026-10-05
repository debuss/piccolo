<?php declare(strict_types=1);

use Application\Http\ErrorLogListener;
use Laminas\Diactoros\{Response, ServerRequest};
use Monolog\Handler\TestHandler;
use Monolog\Logger;

test('logs the error with the request method, URI and response status', function () {
    $handler = new TestHandler();
    $exception = new RuntimeException('boom');

    new ErrorLogListener(new Logger('test', [$handler]))(
        $exception,
        new ServerRequest([], [], 'https://example.com/api/posts?page=2', 'POST'),
        new Response(status: 500)
    );

    $record = $handler->getRecords()[0];

    expect($handler->hasErrorThatContains('boom'))->toBeTrue()
        ->and($record->context)->toBe([
            'exception' => $exception,
            'request' => ['method' => 'POST', 'uri' => 'https://example.com/api/posts?page=2'],
            'response' => ['status' => 500],
        ]);
});

test('logs the original URI when available', function () {
    $handler = new TestHandler();

    // As seen by a middleware piped on "/api", with the original URI kept by OriginalMessages
    $request = new ServerRequest([], [], '/v1/posts/1')
        ->withAttribute('originalUri', new ServerRequest([], [], '/api/v1/posts/1')->getUri());

    new ErrorLogListener(new Logger('test', [$handler]))(new RuntimeException('boom'), $request, new Response());

    expect($handler->getRecords()[0]->context['request']['uri'])->toBe('/api/v1/posts/1');
});

test('does not log headers, cookies nor body', function () {
    $handler = new TestHandler();

    $request = new ServerRequest([], [], '/login', 'POST', 'php://temp', ['Authorization' => 'Bearer secret-token'])
        ->withCookieParams(['session' => 'secret-session'])
        ->withParsedBody(['password' => 'secret-password']);

    new ErrorLogListener(new Logger('test', [$handler]))(new RuntimeException('boom'), $request, new Response());

    $context = json_encode($handler->getRecords()[0]->context['request']);

    expect($context)->not->toContain('secret');
});

<?php declare(strict_types=1);

use Application\Environment;
use Application\Http\ServerRequestErrorResponseGenerator;
use Laminas\Diactoros\ResponseFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

function makeServerRequestErrorResponseGenerator(Environment $environment, ?TestHandler $handler = null): ServerRequestErrorResponseGenerator
{
    return new ServerRequestErrorResponseGenerator(
        new ResponseFactory(),
        new Logger('test', [$handler ?? new TestHandler()]),
        $environment
    );
}

test('returns a 400 plain text response', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(new InvalidArgumentException('boom'));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8');
});

test('only returns the reason phrase in production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(
        new InvalidArgumentException('Invalid header value for X-Token: secret-abc')
    );

    expect((string)$response->getBody())->toBe('Bad Request');
});

test('returns the exception outside of production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Development)(
        new InvalidArgumentException('Invalid header value')
    );

    expect((string)$response->getBody())->toContain('InvalidArgumentException: Invalid header value');
});

test('logs the error', function () {
    $handler = new TestHandler();
    $exception = new InvalidArgumentException('Invalid header value');

    makeServerRequestErrorResponseGenerator(Environment::Production, $handler)($exception);

    expect($handler->hasWarningThatContains('Invalid header value'))->toBeTrue()
        ->and($handler->getRecords()[0]->context['exception'])->toBe($exception);
});

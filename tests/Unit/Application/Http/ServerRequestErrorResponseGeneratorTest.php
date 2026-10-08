<?php declare(strict_types=1);

use Application\Environment;
use Application\Http\ServerRequestErrorResponseGenerator;
use Debuss\ServerRequestFactory\BadRequestException;
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

test('returns a 400 plain text response for a bad request', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(new BadRequestException('boom'));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8');
});

test('only returns the reason phrase in production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(
        new BadRequestException('Invalid header value for X-Token: secret-abc')
    );

    expect((string)$response->getBody())->toBe('Bad Request');
});

test('returns the exception outside of production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Development)(
        new BadRequestException('Invalid header value')
    );

    expect((string)$response->getBody())->toContain('BadRequestException: Invalid header value');
});

test('logs a bad request as a warning', function () {
    $handler = new TestHandler();
    $exception = new BadRequestException('Invalid header value');

    makeServerRequestErrorResponseGenerator(Environment::Production, $handler)($exception);

    expect($handler->hasWarningThatContains('Invalid header value'))->toBeTrue()
        ->and($handler->getRecords()[0]->context['exception'])->toBe($exception);
});

test('returns a 500 plain text response for any other error', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(
        new InvalidArgumentException('Invalid value in uploaded files specification.')
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and((string)$response->getBody())->toBe('Internal Server Error');
});

test('logs any other error as an error', function () {
    $handler = new TestHandler();
    $exception = new RuntimeException('Unable to read the uploaded file');

    makeServerRequestErrorResponseGenerator(Environment::Production, $handler)($exception);

    expect($handler->hasErrorThatContains('Unable to read the uploaded file'))->toBeTrue()
        ->and($handler->hasWarningRecords())->toBeFalse()
        ->and($handler->getRecords()[0]->context['exception'])->toBe($exception);
});

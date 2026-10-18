<?php declare(strict_types=1);

use Application\Http\RequestIdProcessor;
use Application\Middleware\RequestIdMiddleware;
use Laminas\Diactoros\{Response, ServerRequest};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A request handler recording the request it handles, and the identifier the processor holds meanwhile.
 */
function recordingHandler(RequestIdProcessor $processor): RequestHandlerInterface
{
    return new class ($processor) implements RequestHandlerInterface {
        public ?ServerRequestInterface $request = null;
        public ?string $loggedRequestId = null;

        public function __construct(private RequestIdProcessor $processor) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $this->request = $request;
            $this->loggedRequestId = $this->processor->__invoke(makeLogRecord())->extra['request_id'] ?? null;

            return new Response();
        }
    };
}

function makeLogRecord(): Monolog\LogRecord
{
    return new Monolog\LogRecord(new DateTimeImmutable(), 'app', Monolog\Level::Info, 'message');
}

test('generates an identifier when the request has none, for the request, the logs and the response', function () {
    $processor = new RequestIdProcessor();
    $handler = recordingHandler($processor);

    $response = new RequestIdMiddleware($processor)->process(new ServerRequest(), $handler);

    $requestId = $response->getHeaderLine('X-Request-Id');
    expect($requestId)->toMatch('/^[0-9a-f]{32}$/')
        ->and($handler->request->getAttribute('request_id'))->toBe($requestId)
        ->and($handler->loggedRequestId)->toBe($requestId);
});

test('keeps a valid identifier sent in the X-Request-Id header', function (string $requestId) {
    $processor = new RequestIdProcessor();
    $handler = recordingHandler($processor);

    $response = new RequestIdMiddleware($processor)->process(
        new ServerRequest(headers: ['X-Request-Id' => $requestId]),
        $handler
    );

    expect($response->getHeaderLine('X-Request-Id'))->toBe($requestId)
        ->and($handler->request->getAttribute('request_id'))->toBe($requestId);
})->with([
    'a UUID' => ['0b7c4f0e-8d1a-4c5e-9f3b-2a6d8e1f4c7a'],
    'an hexadecimal string' => ['9ca4e1aad6057955ea3dfb40e995e427'],
    'with dots, colons and underscores' => ['lb-01:req_42.3'],
]);

test('replaces an invalid identifier sent in the X-Request-Id header', function (string $requestId) {
    $processor = new RequestIdProcessor();

    $response = new RequestIdMiddleware($processor)->process(
        new ServerRequest(headers: ['X-Request-Id' => $requestId]),
        recordingHandler($processor)
    );

    expect($response->getHeaderLine('X-Request-Id'))->toMatch('/^[0-9a-f]{32}$/');
})->with([
    'with spaces' => ['bad id with spaces'],
    'too long' => [str_repeat('a', 129)],
    'with forbidden characters' => ['<script>'],
]);

test('stops adding the identifier to the logs once the request is handled, even when it fails', function () {
    $processor = new RequestIdProcessor();
    $failingHandler = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new RuntimeException('Failure');
        }
    };

    expect(fn() => new RequestIdMiddleware($processor)->process(new ServerRequest(), $failingHandler))
        ->toThrow(RuntimeException::class)
        ->and($processor(makeLogRecord())->extra)->toBe([]);
});

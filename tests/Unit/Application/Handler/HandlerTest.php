<?php declare(strict_types=1);

use Application\Handler\Handler;
use Laminas\Diactoros\{ResponseFactory, ServerRequest, StreamFactory};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * A concrete handler exposing the protected helpers. The factories are set manually, as the container does after
 * resolution through the Awareness pattern.
 */
function makeHandler(): Handler
{
    $handler = new class extends Handler {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->html('');
        }

        public function callHtml(string $content, int $status = 200): ResponseInterface
        {
            return $this->html($content, $status);
        }

        public function callJson(array|JsonSerializable $data, int $status = 200): ResponseInterface
        {
            return $this->json($data, $status);
        }
    };

    $handler->setResponseFactory(new ResponseFactory());
    $handler->setStreamFactory(new StreamFactory());

    return $handler;
}

test('is a request handler', function () {
    expect(makeHandler()->handle(new ServerRequest()))->toBeInstanceOf(ResponseInterface::class);
});

test('creates an HTML response', function () {
    $response = makeHandler()->callHtml('<h1>Hello</h1>', 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/html; charset=utf-8')
        ->and((string)$response->getBody())->toBe('<h1>Hello</h1>');
});

test('creates a JSON response', function () {
    $response = makeHandler()->callJson(['id' => 1, 'url' => 'https://example.com/a'], 202);

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string)$response->getBody())->toBe('{"id":1,"url":"https://example.com/a"}');
});

test('encodes JsonSerializable objects', function () {
    $data = new class implements JsonSerializable {
        public function jsonSerialize(): array
        {
            return ['serialized' => true];
        }
    };

    expect((string)makeHandler()->callJson($data)->getBody())->toBe('{"serialized":true}');
});

test('keeps unicode and HTML characters as is', function () {
    $body = (string)makeHandler()->callJson(['title' => "Café <b>&</b> l'été"])->getBody();

    expect($body)->toBe('{"title":"Café <b>&</b> l\'été"}');
});

test('throws a JsonException when the data cannot be encoded', function () {
    makeHandler()->callJson(['invalid' => "\xB1\x31"]);
})->throws(JsonException::class);

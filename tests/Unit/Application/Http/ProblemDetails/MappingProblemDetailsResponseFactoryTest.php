<?php declare(strict_types=1);

use Application\Http\ProblemDetails\{ExceptionStatusMapper, MappingProblemDetailsResponseFactory};
use Domain\Shared\Exception\NotFoundException;
use Infrastructure\Shared\Exception\ClientException;
use Laminas\Diactoros\{ResponseFactory, ServerRequest};

function jsonProblemDetailsFactory(): MappingProblemDetailsResponseFactory
{
    return new MappingProblemDetailsResponseFactory(new ResponseFactory(), new ExceptionStatusMapper());
}

test('produces a mapped Problem Details response for a NotFoundException', function () {
    $factory = jsonProblemDetailsFactory();
    $request = (new ServerRequest())->withHeader('Accept', 'application/json');

    $response = $factory->createResponseFromThrowable($request, NotFoundException::create('Post', 42));
    $payload = json_decode((string) $response->getBody(), true);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/problem+json')
        ->and($payload['title'])->toBe('Not Found')
        ->and($payload['status'])->toBe(404)
        ->and($payload['detail'])->toBe('Post "42" could not be found')
        // The type URI must stay a language-neutral, status-derived one (never a per-exception, localized URL).
        ->and($payload['type'])->toBe('https://httpwg.org/specs/rfc9110.html#status.404');
});

test('produces a mapped Problem Details response for a ClientException', function () {
    $factory = jsonProblemDetailsFactory();
    $request = (new ServerRequest())->withHeader('Accept', 'application/json');

    $response = $factory->createResponseFromThrowable($request, ClientException::create('upstream unreachable'));
    $payload = json_decode((string) $response->getBody(), true);

    expect($response->getStatusCode())->toBe(502)
        ->and($payload['title'])->toBe('Bad Gateway')
        ->and($payload['detail'])->toBe('upstream unreachable')
        ->and($payload['type'])->toBe('https://httpwg.org/specs/rfc9110.html#status.502');
});

test('falls back to the parent factory default for an unmapped exception, without leaking its message', function () {
    $factory = jsonProblemDetailsFactory();
    $request = (new ServerRequest())->withHeader('Accept', 'application/json');

    $response = $factory->createResponseFromThrowable($request, new RuntimeException('sensitive internals'));
    $payload = json_decode((string) $response->getBody(), true);

    expect($response->getStatusCode())->toBe(500)
        ->and($payload['title'])->toBe('Internal Server Error')
        ->and($payload['detail'])->not->toContain('sensitive internals');
});

test('exceptions implementing ProblemDetailsExceptionInterface still bypass the mapper entirely', function () {
    $factory = jsonProblemDetailsFactory();
    $request = (new ServerRequest())->withHeader('Accept', 'application/json');

    $exception = new class ('native handling') extends RuntimeException implements
        \Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface
    {
        use \Mezzio\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;

        public function __construct(string $message)
        {
            parent::__construct($message);
            $this->status = 409;
            $this->title = 'Conflict';
            $this->type = 'https://example.com/conflict';
            $this->detail = $message;
        }
    };

    $response = $factory->createResponseFromThrowable($request, $exception);
    $payload = json_decode((string) $response->getBody(), true);

    expect($response->getStatusCode())->toBe(409)
        ->and($payload['title'])->toBe('Conflict')
        ->and($payload['type'])->toBe('https://example.com/conflict');
});

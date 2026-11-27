<?php declare(strict_types=1);

use Application\Handler\Api\PostHandler;
use Domain\Post\{Post, PostClientInterface};
use Domain\Shared\Exception\NotFoundException;
use Mezzio\ProblemDetails\ProblemDetailsMiddleware;
use Piccolo\Http\Exception\NotFoundException as HttpNotFoundException;
use Psr\Http\Message\{ResponseFactoryInterface, StreamFactoryInterface};

/**
 * The handler with a client of a single post (id 1), and the factories of the container.
 */
function postHandler(): PostHandler
{
    $handler = new PostHandler(new class implements PostClientInterface {
        public function getAll(): array
        {
            return [new Post(1, 1, 'Title', 'Body')];
        }

        public function getById(int $id): Post
        {
            return $id === 1 ? new Post(1, 1, 'Title', 'Body') : throw NotFoundException::create('Post', $id);
        }
    });

    $container = client()->container();
    $handler->setResponseFactory($container->get(ResponseFactoryInterface::class));
    $handler->setStreamFactory($container->get(StreamFactoryInterface::class));

    return $handler;
}

test('answers the post', function () {
    $response = postHandler()->handle(client()->createRequest('GET', '/api/v1/posts/1')->withAttribute('id', '1'));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode((string)$response->getBody(), true))->toMatchArray(['id' => 1, 'title' => 'Title']);
});

test('throws an HTTP NotFoundException for an unknown post', function () {
    postHandler()->handle(client()->createRequest('GET', '/api/v1/posts/2')->withAttribute('id', '2'));
})->throws(HttpNotFoundException::class, 'Post "2" could not be found');

test('is answered as a 404 Problem Details response for an unknown post', function () {
    $middleware = client()->container()->get(ProblemDetailsMiddleware::class);

    $response = $middleware->process(
        client()->createRequest('GET', '/api/v1/posts/2', ['Accept' => 'application/json'])->withAttribute('id', '2'),
        postHandler()
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/problem+json')
        ->and(json_decode((string)$response->getBody(), true))->toMatchArray([
            'status' => 404,
            'title' => 'Not Found',
            'detail' => 'Post "2" could not be found',
        ]);
});

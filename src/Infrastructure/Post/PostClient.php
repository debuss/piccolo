<?php declare(strict_types=1);

namespace Infrastructure\Post;

use Psr\Http\Client\ClientExceptionInterface;
use Awareness\{RequestFactoryAwareInterface, RequestFactoryAwareTrait};
use Domain\Post\{Post, PostClientInterface};
use Domain\Shared\Exception\NotFoundException;
use Infrastructure\Shared\Exception\ClientException;
use Psr\Http\Client\ClientInterface;

class PostClient implements PostClientInterface, RequestFactoryAwareInterface
{

    use RequestFactoryAwareTrait;

    private const string URL = 'https://jsonplaceholder.typicode.com/posts';

    public function __construct(
        private readonly ClientInterface $client
    ) {}

    public function getAll(): array
    {
        $request = $this->requestFactory->createRequest('GET', self::URL);

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw ClientException::create($e->getMessage(), previous: $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw ClientException::create(
                sprintf('Upstream request failed with status %d', $response->getStatusCode())
            );
        }

        $response->getBody()->rewind();

        $body = json_decode($response->getBody()->getContents(), true);

        return array_map(static fn(array $result) => Post::fromArray($result), $body);
    }

    public function getById(int $id): Post
    {
        $request = $this->requestFactory->createRequest('GET', self::URL . '/' . $id);

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw ClientException::create($e->getMessage(), previous: $e);
        }

        if ($response->getStatusCode() === 404) {
            throw NotFoundException::create('Post', $id);
        }

        $response->getBody()->rewind();

        $body = json_decode($response->getBody()->getContents(), true);

        return Post::fromArray($body);
    }
}

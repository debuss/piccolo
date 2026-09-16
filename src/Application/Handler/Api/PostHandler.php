<?php declare(strict_types = 1);

namespace Application\Handler\Api;

use Domain\Post\PostClientInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{AsController, Get};

#[AsController('/api/v1')]
readonly class PostHandler implements RequestHandlerInterface
{

    public function __construct(
        private PostClientInterface $client
    ) {}

    #[Get('/posts[/{id:\d+}]', name: 'api.v1.posts.get')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id !== null) {
            return new JsonResponse($this->client->getById((int)$id));
        }

        return new JsonResponse($this->client->getAll());
    }
}

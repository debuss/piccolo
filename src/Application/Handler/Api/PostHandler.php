<?php declare(strict_types=1);

namespace Application\Handler\Api;

use Piccolo\Http\Handler;
use Domain\Post\PostClientInterface;
use Domain\Shared\Exception\NotFoundException;
use Piccolo\Http\Exception\NotFoundException as HttpNotFoundException;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Routing\Attribute\{AsController, Get};

#[AsController('/api/v1')]
class PostHandler extends Handler
{

    public function __construct(
        private PostClientInterface $client
    ) {}

    #[Get('/posts[/{id:\d+}]', name: 'api.v1.posts.get')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return $this->json($this->client->getAll());
        }

        try {
            return $this->json($this->client->getById((int)$id));
        } catch (NotFoundException $e) {
            // Answered as a 404 Problem Details response by the ProblemDetailsMiddleware
            throw new HttpNotFoundException($e->getMessage(), previous: $e);
        }
    }
}

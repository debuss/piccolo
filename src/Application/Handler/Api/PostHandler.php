<?php declare(strict_types=1);

namespace Application\Handler\Api;

use Application\Handler\Handler;
use Domain\Post\PostClientInterface;
use Domain\Shared\Exception\NotFoundException;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{AsController, Get};

#[AsController('/api/v1')]
class PostHandler extends Handler implements RequestHandlerInterface
{

    public function __construct(
        private PostClientInterface $client,
        private ProblemDetailsResponseFactory $problemDetails
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
            return $this->problemDetails->createResponse($request, 404, $e->getMessage());
        }
    }
}

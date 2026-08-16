<?php declare(strict_types=1);

namespace Application\Handler\HealthCheck;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{ApiController, HttpGet};
use function time;

#[ApiController('/api')]
class PingHandler implements RequestHandlerInterface
{

    #[HttpGet('/ping', name: 'api.ping')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['ack' => time()]);
    }
}

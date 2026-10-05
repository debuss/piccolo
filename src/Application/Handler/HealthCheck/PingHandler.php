<?php declare(strict_types=1);

namespace Application\Handler\HealthCheck;

use Application\Handler\Handler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use function time;

class PingHandler extends Handler implements RequestHandlerInterface
{

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json(['ack' => time()]);
    }
}

<?php declare(strict_types=1);

namespace Application\Handler\HealthCheck;

use Application\Handler\Handler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use function time;

class PingHandler extends Handler
{

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json(['ack' => time()]);
    }
}

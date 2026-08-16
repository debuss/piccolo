<?php declare(strict_types=1);

namespace Application\Handler\OpenApi;

use Laminas\Diactoros\Response;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{ApiController, HttpGet};

#[ApiController('/api/v1')]
readonly class OpenApiSpecHandler implements RequestHandlerInterface
{

    public function __construct(
        private StreamFactoryInterface $streamFactory
    ) {}

    #[HttpGet(path: '/openapi[.{format:yml|yaml}]', name: 'api.v1.openapi')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            $this->streamFactory->createStreamFromFile(storage_path('openapi.yaml')),
            headers: [
                'Content-Type' => 'text/yaml',
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 HOUR')),
            ]
        );
    }
}

<?php declare(strict_types=1);

namespace Application\Handler\OpenApi;

use Application\Handler\Handler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Routing\Attribute\{AsController, Get};

#[AsController('/api/v1')]
class OpenApiSpecHandler extends Handler
{

    #[Get(path: '/openapi[.{format:yml|yaml}]', name: 'api.v1.openapi')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory->createResponse(200)
            ->withBody(
                $this->streamFactory->createStreamFromFile(storage_path('openapi.yaml'))
            )
            ->withHeader('Content-Type', 'text/yaml')
            ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 HOUR')));
    }
}

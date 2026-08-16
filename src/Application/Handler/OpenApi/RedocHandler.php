<?php declare(strict_types=1);

namespace Application\Handler\OpenApi;

use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{ApiController, HttpGet};

#[ApiController('/api/v1')]
readonly class RedocHandler implements RequestHandlerInterface
{

    public function __construct(
        private RouterInterface $router,
        private TemplateRendererInterface $renderer
    ) {}

    #[HttpGet('/{redoc:redoc|swagger}', name: 'api.v1.redoc')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse(
            $this->renderer->render('app::redoc', [
                'openapi_url' => $this->router->generateUri('api.v1.openapi')
            ])
        );
    }
}

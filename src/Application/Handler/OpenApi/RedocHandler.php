<?php declare(strict_types=1);

namespace Application\Handler\OpenApi;

use Application\Handler\Handler;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Routing\Attribute\{AsController, Get};

#[AsController('/api/v1')]
class RedocHandler extends Handler
{

    public function __construct(
        private RouterInterface $router,
        private TemplateRendererInterface $renderer
    ) {}

    #[Get('/{redoc:redoc|swagger}', name: 'api.v1.redoc')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->html(
            $this->renderer->render('app::redoc', [
                'openapi_url' => $this->router->generateUri('api.v1.openapi')
            ])
        );
    }
}

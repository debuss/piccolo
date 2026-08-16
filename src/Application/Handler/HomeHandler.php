<?php declare(strict_types=1);

namespace Application\Handler;

use Laminas\Diactoros\Response\{HtmlResponse, JsonResponse};
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{Controller, HttpGet};

#[Controller]
class HomeHandler implements RequestHandlerInterface, LoggerAwareInterface
{

    use LoggerAwareTrait;

    public function __construct(
        private readonly ?TemplateRendererInterface $template = null
    ) {}

    #[HttpGet('/', name: 'home')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger?->info("Home handler started");

        $data = [
            'welcome' => 'Welcome to Piccolo, a streamlined foundation for modern PHP applications.',
            'description' => [
                'Piccolo is a lean re-implementation of the Mezzio Skeleton application, designed for teams that value clarity, maintainability, and pragmatic architecture.',
                'It builds on proven Mezzio and Laminas components while simplifying the integration points that often introduce unnecessary ceremony.',
                'The result is a clean starting point for PSR-based web applications that stays easy to understand as your codebase grows.',
            ],
            'changes' => [
                'Container' => 'Dependency injection is handled by `League Container`, with service providers replacing the sprawling factory and alias configuration often found in larger skeletons.',
                'Configuration' => '`Borsch Config` centralizes configuration from sources such as `.env`, JSON, PHP, and YAML with a lightweight aggregation approach.',
                'DDD' => 'A domain-driven structure keeps application, domain, and infrastructure concerns clearly separated.',
            ],
            'docsUrl' => 'https://github.com/debuss/piccolo',
            'containerName' => 'League Container',
            'containerDocs' => 'https://container.thephpleague.com/5.x/',
            'routerName' => 'Mezzio FastRoute Router',
            'routerDocs' => [
                'Mezzio Router Introduction' => 'https://docs.mezzio.dev/mezzio/v3/features/router/intro/',
                'Mezzio FastRoute Router' => 'https://docs.mezzio.dev/mezzio/v3/features/router/fast-route/',
                'Mezzio Router' => 'https://github.com/mezzio/mezzio-router',
                'Nikic FastRoute' => 'https://github.com/nikic/FastRoute'
            ],
            'loggerName' => 'Monolog',
            'loggerDocs' => 'https://github.com/Seldaek/monolog',
            'cacheName' => 'Scrapbook',
            'cacheDocs' => 'https://www.scrapbook.cash/adapters/',
            'dbName' => 'Laminas-DB',
            'dbDocs' => 'https://docs.laminas.dev/laminas-db/',
            'pbDetailsName' => 'Mezzio Problem Details',
            'pbDetailsDocs' => 'https://docs.mezzio.dev/mezzio-problem-details/'
        ];

        if ($this->template === null) {
            return new JsonResponse($data);
        }

        $data['templateName'] = 'Plates';
        $data['templateDocs'] = 'https://platesphp.com/';

        return new HtmlResponse($this->template->render('app::home-page', $data));
    }
}

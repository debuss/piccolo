<?php declare(strict_types=1);

namespace Application\Handler;

use Application\Environment;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Routing\Attribute\{AsController, Get};

/**
 * Welcome page: this handler and storage/templates/home-page.php can be deleted once you no longer need them.
 */
#[AsController]
class HomeHandler extends Handler implements RequestHandlerInterface, LoggerAwareInterface
{

    use LoggerAwareTrait;

    public function __construct(
        private readonly Environment $environment,
        private readonly ?TemplateRendererInterface $template = null
    ) {}

    #[Get('/', name: 'home')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->logger?->info('Home handler started');

        $data = [
            'php' => PHP_VERSION,
            'environment' => $this->environment->value,
        ];

        if ($this->template === null) {
            return $this->json($data);
        }

        $start = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? null;
        $data['duration'] = is_float($start) ? (int)round((microtime(true) - $start) * 1000) : null;

        return $this->html($this->template->render('app::home-page', $data));
    }
}

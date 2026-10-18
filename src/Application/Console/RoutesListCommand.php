<?php declare(strict_types=1);

namespace Application\Console;

use Application\Application;
use Application\Middleware\LazyLoadingMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RoutesListCommand
 *
 * Lists the routes registered by `config/routes.php` (from the handler attributes and the manual ones), in their order
 * of registration, with the middlewares of each route followed by its handler.
 */
#[AsCommand(name: 'routes:list', description: 'Lists the routes of the application')]
class RoutesListCommand extends Command
{

    public function __construct(
        private readonly Application $application,
        private readonly ContainerInterface $container
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (require config_path('routes.php'))($this->application, $this->container);

        $rows = [];
        foreach ($this->application->getRoutes() as $route) {
            $rows[] = [
                $route->getName(),
                implode('|', $route->getAllowedMethods() ?? ['ANY']),
                $route->getPath(),
                $this->describe($route->getMiddleware()),
            ];
        }

        (new SymfonyStyle($input, $output))->table(['Name', 'Methods', 'Path', 'Middlewares and handler'], $rows);

        return Command::SUCCESS;
    }

    /**
     * The routes with middlewares are a pipe of the middlewares followed by the handler, the others are the handler.
     */
    private function describe(MiddlewareInterface $middleware): string
    {
        return match (true) {
            $middleware instanceof MiddlewarePipe => implode(
                ' → ',
                array_map($this->describe(...), iterator_to_array($middleware, false))
            ),
            $middleware instanceof LazyLoadingMiddleware => $middleware->getMiddleware(),
            default => $middleware::class,
        };
    }
}

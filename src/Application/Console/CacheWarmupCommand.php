<?php declare(strict_types=1);

namespace Application\Console;

use Application\{Application, Environment};
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{ArrayInput, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CacheWarmupCommand
 *
 * Regenerates the caches enabled in production (the route definitions read from the handler attributes, and the
 * FastRoute dispatch data), to be run on each deployment: the routes are then never scanned nor compiled by a request,
 * and the cache directory can be read-only.
 *
 * The services are resolved from the container once the previous caches are removed, as the router loads its cache
 * when it is created.
 */
#[AsCommand(name: 'cache:warmup', description: 'Regenerates the production caches (routes)')]
class CacheWarmupCommand extends Command
{

    public function __construct(
        private readonly Environment $environment,
        private readonly CacheClearCommand $cacheClear,
        private readonly ContainerInterface $container
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->environment->isProduction()) {
            $io->note('The caches are only enabled in production (APP_ENV=production), there is nothing to warm up.');

            return Command::SUCCESS;
        }

        $this->cacheClear->run(new ArrayInput([]), $output);

        // Registering the routes generates the cache of the route definitions (see config/routes.php), then the first
        // match compiles them for FastRoute, which writes its own cache.
        $application = $this->container->get(Application::class);
        (require config_path('routes.php'))($application, $this->container);

        $request = $this->container->get(ServerRequestFactoryInterface::class)->createServerRequest('GET', '/');
        $this->container->get(RouterInterface::class)->match($request);

        $io->success('The caches have been warmed up.');

        return Command::SUCCESS;
    }
}

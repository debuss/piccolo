<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Environment;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Mezzio\Router\{FastRouteRouter, RouterInterface};

/**
 * FastRoute Router Service Provider
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/router/fast-route/
 */
class FastRouteRouterServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            FastRouteRouter::class,
            RouterInterface::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                FastRouteRouter::class,
                static fn (Environment $environment): FastRouteRouter => new FastRouteRouter(config: [
                    FastRouteRouter::CONFIG_CACHE_ENABLED => $environment->isProduction(),
                    FastRouteRouter::CONFIG_CACHE_FILE => cache_path('routes.cache.php')
                ])
            )
            ->addArgument(Environment::class);

        $this->getContainer()->add(RouterInterface::class, FastRouteRouter::class);
    }
}

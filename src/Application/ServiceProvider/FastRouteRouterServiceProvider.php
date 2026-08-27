<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\Config;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Mezzio\Router\{FastRouteRouter, RouterInterface};
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};

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

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                FastRouteRouter::class,
                static fn (Config $config): FastRouteRouter => new FastRouteRouter(config: [
                    FastRouteRouter::CONFIG_CACHE_ENABLED => $config->get('APP_ENV') === 'production',
                    FastRouteRouter::CONFIG_CACHE_FILE => cache_path('routes.cache.php')
                ])
            )
            ->addArgument($this->getContainer()->get(Config::class));

        $this->getContainer()->add(RouterInterface::class, FastRouteRouter::class);
    }
}

<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Awareness\{CacheAwareInterface, CacheItemPoolAwareInterface};
use League\Container\ServiceProvider\{AbstractServiceProvider, BootableServiceProviderInterface};
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-6 Cache and PSR-16 Simple Cache Service Provider
 *
 * @see https://www.scrapbook.cash/
 */
class CacheServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{

    /**
     * For every class implementing the CacheItemPoolAwareInterface, inject the cache item pool instance into the class.
     * For every class implementing the CacheAwareInterface, inject the cache instance into the class.
     */
    public function boot(): void
    {
        $container = $this->getContainer();

        $container->afterResolve(
            CacheItemPoolAwareInterface::class,
            static fn (CacheItemPoolAwareInterface $class) => $class->setCacheItemPool(
                $container->get(CacheItemPoolInterface::class)
            )
        );

        $container->afterResolve(
            CacheAwareInterface::class,
            static fn (CacheAwareInterface $class) => $class->setCache(
                $container->get(CacheInterface::class)
            )
        );
    }

    public function provides(string $id): bool
    {
        return in_array($id, [
            CacheItemPoolInterface::class,
            CacheInterface::class
        ]);
    }

    public function register(): void
    {
        // Using a simple in-memory cache store.
        // Replace it with a store adapted to your needs (Redis, SQLite, Filesystem, Memcached, ...).
        // See https://www.scrapbook.cash/
        $cache = new MemoryStore();

        $this
            ->getContainer()
            ->add(CacheItemPoolInterface::class, fn (): CacheItemPoolInterface => new Pool($cache));

        $this
            ->getContainer()
            ->add(CacheInterface::class, fn (): CacheInterface => new SimpleCache($cache));
    }
}

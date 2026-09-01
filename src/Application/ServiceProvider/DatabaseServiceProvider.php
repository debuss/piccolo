<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\Config;
use Laminas\Db\Adapter\Adapter;
use League\Container\ServiceProvider\AbstractServiceProvider;
use PDO;
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};

/**
 * Database Service Provider
 *
 * Provides a Laminas DB Adapter instance configured with the application's database settings.
 *
 * @see https://docs.laminas.dev/laminas-db/adapter/
 */
class DatabaseServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return $id === Adapter::class;
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
                Adapter::class,
                static fn (Config $config) => new Adapter([
                    'driver' => $config->get('DB_DRIVER'),
                    'database' => storage_path($config->get('DB_DATABASE')),
                    'driver_options' => [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ],
                ])
            )
            ->addArgument(Config::class);
    }
}

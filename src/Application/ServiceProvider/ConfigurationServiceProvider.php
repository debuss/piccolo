<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\{Aggregator, Config};
use Borsch\Config\Provider\DotEnvProvider;
use Borsch\Config\Exception\AggregatorException;
use Closure;
use League\Container\ServiceProvider\AbstractServiceProvider;

/**
 * Configuration Service Provider
 *
 * A lightweight and easy to use configuration class, with support for multiple file formats (JSON, YAML, INI, DOTENV)
 * that can aggregate multiple configuration sources and uses caching for improved performance in production.
 *
 * @see https://github.com/borschphp/borsch-config
 */
class ConfigurationServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return $id === Config::class;
    }

    /**
     * @throws AggregatorException
     */
    public function register(): void
    {
        $aggregator = new Aggregator(
            [
                new DotEnvProvider(app_path('.env'))
            ],
            cache_path('config.cache.php'),
            // Use an environment variable to define the app environment, so that the configuration can be cached.
            ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development') === 'production'
        );

        $this->getContainer()->add(Config::class, static fn (): Config => $aggregator->getMergedConfig());
    }
}

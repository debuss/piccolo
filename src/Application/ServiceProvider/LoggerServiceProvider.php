<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Environment;
use Borsch\Config\Config;
use League\Container\ServiceProvider\{AbstractServiceProvider, BootableServiceProviderInterface};
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\{Level, Logger};
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};

/**
 * PSR-3 Logger Service Provider
 *
 * Logs are written as JSON lines to a single stream, configured with environment variables:
 * - LOG_STREAM: `php://stderr` by default (Docker, systemd, ...), or a file path (relative to the app root)
 * - LOG_LEVEL: minimum level to log, `debug` by default, `info` in production
 *
 * @see https://seldaek.github.io/monolog/
 */
class LoggerServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{

    /**
     * For every class implementing the LoggerAwareInterface, inject the logger instance into the class and change the
     * name to the class name so that you know from where the log is originated.
     */
    public function boot(): void
    {
        $container = $this->getContainer();

        $container->afterResolve(
            LoggerAwareInterface::class,
            static function (LoggerAwareInterface $class) use ($container): void {
                $logger = $container->get(LoggerInterface::class);
                if ($logger instanceof Logger) {
                    $logger = $logger->withName(get_class($class));
                }

                $class->setLogger($logger);
            }
        );
    }

    public function provides(string $id): bool
    {
        return in_array($id, [
            Logger::class,
            LoggerInterface::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(Logger::class, static function (Config $config, Environment $environment): Logger {
                $level = Level::fromName($config->getOrDefault('LOG_LEVEL', $environment->isProduction() ? 'info' : 'debug'));

                $stream = $config->getOrDefault('LOG_STREAM', 'php://stderr');
                if (!str_contains($stream, '://') && !preg_match('#^([a-z]:)?[\\\\/]#i', $stream)) {
                    $stream = app_path($stream);
                }

                return new Logger(
                    $config->getOrDefault('LOGGER_NAME', 'app'),
                    [new StreamHandler($stream, $level)->setFormatter(new JsonFormatter())],
                    [new PsrLogMessageProcessor(removeUsedContextFields: true)]
                );
            })
            ->addArguments([
                Config::class,
                Environment::class
            ]);

        $this->getContainer()->add(LoggerInterface::class, Logger::class);
    }
}

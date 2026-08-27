<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\Config;
use League\Container\ServiceProvider\{AbstractServiceProvider, BootableServiceProviderInterface};
use DateTimeZone;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\{Level, Logger};
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};

/**
 * PSR-3 Logger Service Provider
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

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $this
            ->getContainer()
            ->add(Logger::class, static function (Config $config): Logger {
                $name = $config->getOrDefault('LOGGER_NAME', 'app');
                $tz = $config->getOrDefault('TIMEZONE', 'UTC');

                $formatter = new JsonFormatter();
                $handlers = [
                    // Log important errors in a file
                    new StreamHandler(logs_path('app.log'), Level::Warning)
                        ->setFormatter($formatter),

                    // Log everything to the console (useful when running in Docker)
                    new StreamHandler(fopen('php://stdout', 'wb'), Level::Debug)
                        ->setFormatter($formatter),
                ];

                $processors = [
                    new PsrLogMessageProcessor(removeUsedContextFields: true)
                ];

                $timezone = new DateTimeZone($tz);

                return new Logger($name, $handlers, $processors, $timezone);
            })
            ->addArgument($this->getContainer()->get(Config::class));

        $this->getContainer()->add(LoggerInterface::class, Logger::class);
    }
}

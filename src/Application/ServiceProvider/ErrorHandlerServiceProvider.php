<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Environment;
use Application\Http\ErrorLogListener;
use Laminas\Stratigility\Middleware\{ErrorHandler, ErrorResponseGenerator};
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Error Handler Service Provider
 *
 * This service provider registers the error handler middleware in the container.
 * Errors are logged with the provided logger, and the response only contains the exception details (message and
 * trace) outside of production, otherwise a generic reason phrase is returned.
 *
 * @see https://docs.laminas.dev/laminas-stratigility/v4/error-handlers/
 */
class ErrorHandlerServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return $id === ErrorHandler::class;
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                ErrorHandler::class,
                static function (
                    ResponseFactoryInterface $responseFactory,
                    Environment $environment,
                    ErrorLogListener $listener
                ): ErrorHandler {
                    $handler = new ErrorHandler(
                        $responseFactory,
                        new ErrorResponseGenerator(isDevelopmentMode: !$environment->isProduction())
                    );

                    $handler->attachListener($listener);

                    return $handler;
                }
            )
            ->addArguments([
                ResponseFactoryInterface::class,
                Environment::class,
                ErrorLogListener::class
            ]);
    }
}

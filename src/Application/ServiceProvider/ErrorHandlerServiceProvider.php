<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Environment;
use Laminas\Stratigility\Middleware\{ErrorHandler, ErrorResponseGenerator};
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\{ResponseFactoryInterface,
    ResponseInterface,
    ServerRequestInterface};
use Psr\Log\LoggerInterface;
use Throwable;

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
                    LoggerInterface $logger
                ): ErrorHandler {
                    $handler = new ErrorHandler(
                        $responseFactory,
                        new ErrorResponseGenerator(isDevelopmentMode: !$environment->isProduction())
                    );

                    $handler->attachListener(static fn (
                        Throwable $e,
                        ServerRequestInterface $request,
                        ResponseInterface $response
                    ) => $logger->error($e->getMessage(), ['exception' => $e, 'request' => $request, 'response' => $response]));

                    return $handler;
                }
            )
            ->addArguments([
                ResponseFactoryInterface::class,
                Environment::class,
                LoggerInterface::class
            ]);
    }
}

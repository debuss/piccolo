<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\Config;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Laminas\Stratigility\Utils;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\{ResponseFactoryInterface,
    ResponseInterface,
    ServerRequestInterface};
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Error Handler Service Provider
 *
 * This service provider registers the error handler and the Whoops error handling library in the container.
 * It provides a custom error handler that generates a response based on the exception thrown and logs the error using
 * the provided logger.
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/error-handling/
 */
class ErrorHandlerServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            Run::class,
            ErrorHandler::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                Run::class,
                static function (Config $config): Run {
                    $whoops = new Run();

                    $whoops->allowQuit(false);
                    $whoops->writeToOutput(false);
                    $whoops->sendHttpCode(false);

                    $whoops->pushHandler(
                        $config->getOrDefault('APP_ENV', 'development') === 'production'
                            ? new PlainTextHandler()
                            : new PrettyPageHandler()
                    );

                    $whoops->register();

                    return $whoops;
                }
            )
            ->addArgument(Config::class);

        $this
            ->getContainer()
            ->add(
                ErrorHandler::class,
                static function (ResponseFactoryInterface $responseFactory, Run $whoops, LoggerInterface $logger) {
                    $responseGenerator = static function (
                        Throwable $e,
                        ServerRequestInterface $request,
                        ResponseInterface $response
                    ) use ($whoops): ResponseInterface {
                        foreach ($whoops->getHandlers() as $handler) {
                            if ($handler instanceof PrettyPageHandler) {
                                $handler->addDataTable('Application Request', [
                                    'HTTP Method' => $request->getMethod(),
                                    'URI' => (string)$request->getUri(),
                                    'Script' => $request->getServerParams()['SCRIPT_NAME'] ?? '',
                                    'Headers' => $request->getHeaders(),
                                    'Cookies' => $request->getCookieParams(),
                                    'Attributes' => $request->getAttributes(),
                                    'Query String Arguments' => $request->getQueryParams(),
                                    'Body Params' => $request->getParsedBody()
                                ]);
                            }
                        }

                        $response = $response->withStatus(Utils::getStatusCode($e, $response));

                        $response->getBody()->write($whoops->handleException($e));

                        return $response;
                    };

                    $handler = new ErrorHandler($responseFactory, $responseGenerator);

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
                Run::class,
                LoggerInterface::class
            ]);
    }
}

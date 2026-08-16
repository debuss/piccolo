<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Mezzio\ProblemDetails\{ProblemDetailsMiddleware, ProblemDetailsResponseFactory};
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Throwable;

/**
 * Problem Details Service Provider
 *
 * @see https://docs.mezzio.dev/mezzio-problem-details/intro/
 */
class ProblemDetailsServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            ProblemDetailsResponseFactory::class,
            ProblemDetailsMiddleware::class
        ]);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                ProblemDetailsResponseFactory::class,
                static fn (ResponseFactoryInterface $responseFactory) => new ProblemDetailsResponseFactory($responseFactory)
            )
            ->addArgument($this->getContainer()->get(ResponseFactoryInterface::class));

        $this
            ->getContainer()
            ->add(
                ProblemDetailsMiddleware::class,
                static function (ProblemDetailsResponseFactory $factory, LoggerInterface $logger) {
                    $middleware = new ProblemDetailsMiddleware($factory);

                    $middleware->attachListener(
                        static function (Throwable $error, ServerRequestInterface $request, ResponseInterface $response) use ($logger) {
                            $logger->error($error->getMessage(), [
                                'exception' => $error,
                                'request' => [
                                    'method' => $request->getMethod(),
                                    'uri' => (string)$request->getUri(),
                                    'script' => $request->getServerParams()['SCRIPT_NAME'] ?? '',
                                    'headers' => $request->getHeaders(),
                                    'cookies' => $request->getCookieParams(),
                                    'attributes' => $request->getAttributes(),
                                    'query_params' => $request->getQueryParams(),
                                    'body_params' => $request->getParsedBody(),
                                ],
                                'response' => [
                                    'code' => $response->getStatusCode(),
                                    'headers' => $response->getHeaders(),
                                    'body' => $response->getBody()->getContents()
                                ]
                            ]);

                            $response->getBody()->rewind(); // Rewind the response body to allow further reading
                        }
                    );

                    return $middleware;
                }
            )
            ->addArguments([
                $this->getContainer()->get(ProblemDetailsResponseFactory::class),
                $this->getContainer()->get(LoggerInterface::class)
            ]);
    }
}

<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Environment;
use Application\Http\ErrorLogListener;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Mezzio\ProblemDetails\{ProblemDetailsMiddleware, ProblemDetailsResponseFactory};
use Psr\Http\Message\ResponseFactoryInterface;

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

    public function register(): void
    {
        // Exceptions that do not implement ProblemDetailsExceptionInterface become a 500 response, their message and
        // trace are only exposed in debug mode (outside of production).
        $this
            ->getContainer()
            ->add(
                ProblemDetailsResponseFactory::class,
                static fn (ResponseFactoryInterface $responseFactory, Environment $environment) =>
                    new ProblemDetailsResponseFactory($responseFactory, isDebug: !$environment->isProduction())
            )
            ->addArguments([
                ResponseFactoryInterface::class,
                Environment::class
            ]);

        $this
            ->getContainer()
            ->add(
                ProblemDetailsMiddleware::class,
                static function (ProblemDetailsResponseFactory $factory, ErrorLogListener $listener) {
                    $middleware = new ProblemDetailsMiddleware($factory);
                    $middleware->attachListener($listener);

                    return $middleware;
                }
            )
            ->addArguments([
                ProblemDetailsResponseFactory::class,
                ErrorLogListener::class
            ]);
    }
}

<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Application\Http\{ServerRequestCreator, ServerRequestErrorResponseGenerator};
use Laminas\HttpHandlerRunner\{Emitter\SapiEmitter, RequestHandlerRunner, RequestHandlerRunnerInterface};
use Laminas\Stratigility\{MiddlewarePipeInterface, MiddlewarePipe};
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Throwable;

/**
 * Request Handler Runner Service Provider
 *
 * @see https://docs.laminas.dev/laminas-httphandlerrunner/runner/
 */
class RequestHandlerRunnerServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            RequestHandlerRunner::class,
            RequestHandlerRunnerInterface::class,
            MiddlewarePipe::class,
            MiddlewarePipeInterface::class
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function register(): void
    {
        // Using this pipeline here for both definitions as it will be loaded by RequestHandlerRunner and Application.
        $pipeline = new MiddlewarePipe();

        $this->container->add(MiddlewarePipe::class, $pipeline);
        $this->container->add(MiddlewarePipeInterface::class, MiddlewarePipe::class);

        $container = $this->getContainer();

        $this
            ->getContainer()
            ->add(RequestHandlerRunner::class)
            ->addArgument($pipeline)
            ->addArgument(new SapiEmitter)
            ->addArgument(
                static fn (): ServerRequestInterface => $container->get(ServerRequestCreator::class)->fromGlobals()
            )
            // Only resolved when the server request cannot be created, to avoid building its dependencies on each request
            ->addArgument(
                static fn (Throwable $e): ResponseInterface => $container->get(ServerRequestErrorResponseGenerator::class)($e)
            );

        $this
            ->getContainer()
            ->add(RequestHandlerRunnerInterface::class, RequestHandlerRunner::class);
    }
}

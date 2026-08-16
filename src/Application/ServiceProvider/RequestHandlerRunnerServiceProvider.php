<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Laminas\Diactoros\{ResponseFactory, ServerRequestFactory};
use Laminas\HttpHandlerRunner\{Emitter\SapiEmitter, RequestHandlerRunner, RequestHandlerRunnerInterface};
use Laminas\Stratigility\{MiddlewarePipeInterface, MiddlewarePipe};
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestFactoryInterface, ServerRequestInterface};
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
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        // Using this pipeline here for both definitions as it will be loaded by RequestHandlerRunner and Application.
        // The pipeline needs to be shared in order to inject middlewares from Application class, then used in
        // RequestHandlerRunner::run().
        $pipeline = new MiddlewarePipe();

        $this->container->add(MiddlewarePipe::class, $pipeline);
        $this->container->add(MiddlewarePipeInterface::class, MiddlewarePipe::class);

        $serverRequestFactory = $this->container->get(ServerRequestFactoryInterface::class);

        $this
            ->getContainer()
            ->add(RequestHandlerRunner::class)
            ->addArgument($pipeline)
            ->addArgument(new SapiEmitter)
            ->addArgument(static fn (): ServerRequestInterface => $serverRequestFactory::fromGlobals())
            ->addArgument(static function (Throwable $e): ResponseInterface {
                $response = (new ResponseFactory())->createResponse(500);
                $response->getBody()->write(sprintf(
                    'An error occurred: %s',
                    $e->getMessage()
                ));

                return $response;
            });

        $this
            ->getContainer()
            ->add(RequestHandlerRunnerInterface::class, RequestHandlerRunner::class);
    }
}
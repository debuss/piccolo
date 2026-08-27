<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Awareness\{RequestFactoryAwareInterface,
    ResponseFactoryAwareInterface,
    ServerRequestFactoryAwareInterface,
    StreamFactoryAwareInterface};
use Laminas\Diactoros\{RequestFactory, ResponseFactory, ServerRequestFactory, StreamFactory};
use League\Container\ServiceProvider\{BootableServiceProviderInterface, AbstractServiceProvider};
use Psr\Http\Message\{RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface};

/**
 * PSR-17 HTTP Factories Service Providers
 *
 * @see https://docs.laminas.dev/laminas-diactoros/v3/factories/
 */
class HttpFactoryServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{

    /**
     * For every class implementing the ResponseFactoryAwareInterface, inject the response factory instance into the
     * class.
     * For every class implementing the StreamFactoryAwareInterface, inject the stream factory instance into the class.
     * For every class implementing the ServerRequestFactoryAwareInterface, inject the server request factory instance
     * into the class.
     */
    public function boot(): void
    {
        $container = $this->getContainer();

        $container->afterResolve(
            ResponseFactoryAwareInterface::class,
            static fn (ResponseFactoryAwareInterface $class) => $class->setResponseFactory(
                $container->get(ResponseFactoryInterface::class)
            )
        );

        $container->afterResolve(
            StreamFactoryAwareInterface::class,
            static fn (StreamFactoryAwareInterface $class) => $class->setStreamFactory(
                $container->get(StreamFactoryInterface::class)
            )
        );

        $container->afterResolve(
            RequestFactoryAwareInterface::class,
            static fn (RequestFactoryAwareInterface $class) => $class->setRequestFactory(
                $container->get(RequestFactoryInterface::class)
            )
        );

        $container->afterResolve(
            ServerRequestFactoryAwareInterface::class,
            static fn (ServerRequestFactoryAwareInterface $class) => $class->setServerRequestFactory(
                $container->get(ServerRequestFactoryInterface::class)
            )
        );
    }

    public function provides(string $id): bool
    {
        return in_array($id, [
            StreamFactoryInterface::class,
            ResponseFactoryInterface::class,
            RequestFactoryInterface::class,
            ServerRequestFactoryInterface::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(StreamFactoryInterface::class, static fn (): StreamFactoryInterface => new StreamFactory());

        $this
            ->getContainer()
            ->add(ResponseFactoryInterface::class, static fn (): ResponseFactoryInterface => new ResponseFactory());

        $this
            ->getContainer()
            ->add(RequestFactoryInterface::class, static fn (): RequestFactoryInterface => new RequestFactory());

        $this
            ->getContainer()
            ->add(ServerRequestFactoryInterface::class, static fn (): ServerRequestFactoryInterface => new ServerRequestFactory());
    }
}

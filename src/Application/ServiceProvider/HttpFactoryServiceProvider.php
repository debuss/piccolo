<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Awareness\{RequestFactoryAwareInterface,
    ResponseFactoryAwareInterface,
    ServerRequestFactoryAwareInterface,
    StreamFactoryAwareInterface,
    UploadedFileFactoryAwareInterface,
    UriFactoryAwareInterface};
use League\Container\ServiceProvider\{BootableServiceProviderInterface, AbstractServiceProvider};
use Psr\Http\Message\{RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface};

/**
 * PSR-17 HTTP Factories Service Providers
 *
 * @see https://docs.laminas.dev/laminas-diactoros/v3/factories/
 */
class HttpFactoryServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{

    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ServerRequestFactoryInterface $serverRequestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly UploadedFileFactoryInterface $uploadedFileFactory,
        private readonly UriFactoryInterface $uriFactory
    ) {}

    public function provides(string $id): bool
    {
        return in_array($id, [
            StreamFactoryInterface::class,
            ResponseFactoryInterface::class,
            RequestFactoryInterface::class,
            ServerRequestFactoryInterface::class,
            UriFactoryInterface::class,
            UploadedFileFactoryInterface::class
        ]);
    }

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

        $container->afterResolve(
            UriFactoryAwareInterface::class,
            static fn (UriFactoryAwareInterface $class) => $class->setUriFactory(
                $container->get(UriFactoryInterface::class)
            )
        );

        $container->afterResolve(
            UploadedFileFactoryAwareInterface::class,
            static fn (UploadedFileFactoryAwareInterface $class) => $class->setUploadedFileFactory(
                $container->get(UploadedFileFactoryInterface::class)
            )
        );
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(StreamFactoryInterface::class, $this->streamFactory);

        $this
            ->getContainer()
            ->add(ResponseFactoryInterface::class, $this->responseFactory);

        $this
            ->getContainer()
            ->add(RequestFactoryInterface::class, $this->requestFactory);

        $this
            ->getContainer()
            ->add(ServerRequestFactoryInterface::class, $this->serverRequestFactory);

        $this
            ->getContainer()
            ->add(UriFactoryInterface::class, $this->uriFactory);

        $this
            ->getContainer()
            ->add(UploadedFileFactoryInterface::class, $this->uploadedFileFactory);
    }
}

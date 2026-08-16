<?php declare(strict_types=1);

namespace Application\Middleware;

use Psr\Container\{ContainerExceptionInterface, ContainerInterface, NotFoundExceptionInterface};
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

readonly class LazyLoadingMiddleware implements MiddlewareInterface
{

    public function __construct(
        private ContainerInterface $container,
        private string $middleware
    ) {}

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middleware = $this->container->get($this->middleware);
        if ($middleware instanceof RequestHandlerInterface && ! $middleware instanceof MiddlewareInterface) {
            $middleware = new RequestHandlerMiddleware($middleware);
        }

        return $middleware->process($request, $handler);
    }
}

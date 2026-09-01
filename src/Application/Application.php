<?php declare(strict_types=1);

namespace Application;

use Application\Middleware\LazyLoadingMiddleware;
use InvalidArgumentException;
use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use Laminas\Stratigility\Middleware\{CallableMiddlewareDecorator, RequestHandlerMiddleware};
use Laminas\Stratigility\MiddlewarePipeInterface;
use Mezzio\Router\{Route, RouteCollector};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Psr\Container\{ContainerExceptionInterface, ContainerInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use function Laminas\Stratigility\path;

/**
 * Application
 *
 * An almost copy-carbon of Mezzio\Application class, but with the ability to inject middlewares and request handlers
 * from the container (via the method `toMiddleware()` instead of using a `MiddlewareFactoryInterface` class).
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/application/
 */
final readonly class Application implements MiddlewareInterface, RequestHandlerInterface
{

    public function __construct(
        private MiddlewarePipeInterface $pipeline,
        private RequestHandlerRunnerInterface $runner,
        private RouteCollector $routes,
        private ContainerInterface $container
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->pipeline->process($request, $handler);
    }

    public function run(): void
    {
        $this->runner->run();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function pipe(
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middlewareOrPath,
        null|string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware = null
    ): void {
        $middleware ??= $middlewareOrPath;
        $path = $middleware === $middlewareOrPath ? '/' : $middlewareOrPath;

        if (is_array($middleware)) {
            foreach ($middleware as $middle) {
                $this->pipe($path, $middle);
            }
            return;
        }

        $middleware = $path !== '/'
            ? path($path, $this->toMiddleware($middleware))
            : $this->toMiddleware($middleware);

        $this->pipeline->pipe($middleware);
    }

    private function toMiddleware(
        string|callable|MiddlewareInterface|RequestHandlerInterface $middleware
    ): MiddlewareInterface {
        if (is_string($middleware) && class_exists($middleware)) {
            $middleware = new LazyLoadingMiddleware($this->container, $middleware);
        } elseif (is_callable($middleware)) {
            $middleware = new CallableMiddlewareDecorator($middleware);
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerMiddleware($middleware);
        }

        if (is_string($middleware) && !$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Middleware must be an instance of "%s" or "%s", "%s" given',
                    MiddlewareInterface::class,
                    RequestHandlerInterface::class,
                    $middleware
                )
            );
        }

        return $middleware;
    }

    public function route(string $path, $middleware, ?array $methods = null, ?string $name = null): Route
    {
        return $this->routes->route($path, $this->toMiddleware($middleware), $methods, $name);
    }

    public function get(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    public function post(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    public function put(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    public function patch(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    public function delete(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    public function any(string $path, $middleware, ?string $name = null): Route
    {
        return $this->route($path, $middleware,  null, $name);
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes->getRoutes();
    }
}

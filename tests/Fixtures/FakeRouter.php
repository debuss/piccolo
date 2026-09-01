<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Mezzio\Router\{Route, RouteResult, RouterInterface};
use Psr\Http\Message\ServerRequestInterface;

/**
 * Minimal RouterInterface implementation for testing RouteCollector-dependent code without pulling in a real
 * routing algorithm (FastRoute, ...). Only records added routes; matching/URI generation are unused by these tests.
 */
final class FakeRouter implements RouterInterface
{

    /** @var Route[] */
    public array $addedRoutes = [];

    public function addRoute(Route $route): void
    {
        $this->addedRoutes[] = $route;
    }

    public function match(ServerRequestInterface $request): RouteResult
    {
        throw new \LogicException('FakeRouter::match() is not used by these tests.');
    }

    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        throw new \LogicException('FakeRouter::generateUri() is not used by these tests.');
    }
}

<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Psr\Container\{ContainerInterface, NotFoundExceptionInterface};

/**
 * Minimal PSR-11 container backed by a plain array of pre-built instances, so tests can control exactly what
 * a class name resolves to (e.g. via LazyLoadingMiddleware) without booting the real League Container.
 */
final class FakeContainer implements ContainerInterface
{

    public function __construct(
        private array $bindings = []
    ) {}

    public function set(string $id, object $instance): void
    {
        $this->bindings[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ("No entry found for \"$id\"") extends \Exception implements NotFoundExceptionInterface {};
        }

        return $this->bindings[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }
}

# Copilot Instructions

Piccolo is a PHP 8.4+ application skeleton built on Laminas Stratigility, Mezzio Router, and League Container.
It follows Domain-Driven Design conventions and PSR standards throughout (PSR-3, PSR-6, PSR-7, PSR-11, PSR-15, PSR-16, PSR-17, PSR-18).

Read these instructions before suggesting or generating any code.

---

## Architecture

### Layer boundaries

```
src/
├── Application/   # HTTP delivery: handlers, middleware, service providers
├── Domain/        # Business contracts: interfaces, models, exceptions, shared types
└── Infrastructure # External IO: HTTP clients, persistence adapters
```

**Rules — never break them:**
- `Domain` must not depend on `Application` or `Infrastructure`.
- `Infrastructure` may depend on `Domain` only.
- `Application` may depend on `Domain` interfaces; it must not instantiate `Infrastructure` classes directly.
- Shared cross-domain concepts (e.g. `PageResult`, `NotFoundException`) belong in `Domain\Shared`, not in any specific domain namespace.

---

## PHP conventions

- Every PHP file must start with `<?php declare(strict_types=1);`.
- Use `final readonly class` for domain value objects and models.
- Use `readonly class` for handlers and infrastructure classes that are not extended.
- Constructor property promotion is the standard; never declare properties separately unless unavoidable.
- Always use named arguments for clarity when calling factory methods with multiple parameters.
- Prefer `match` over `switch`. Prefer early returns over nested `if` blocks.
- Never suppress exceptions silently except in health check probes.

---

## Handlers

Handlers implement `Psr\Http\Server\RequestHandlerInterface` and live in `src/Application/Handler`.  
They are organized in sub-namespaces by concern (e.g. `Application\Handler\Api`, `Application\Handler\HealthCheck`, `Application\Handler\OpenApi`).

**Routing is attribute-based.** Always declare routes with attributes:

```php
#[Controller]               // for web routes — no path prefix
#[ApiController('/api/v1')] // for API routes — applies a prefix to all methods inside

#[HttpGet('/posts[/{id:\d+}]', name: 'api.v1.posts.get')]
#[HttpPost('/posts', name: 'api.v1.posts.create')]
```

- Use `#[Controller]` for HTML/generic handlers.
- Use `#[ApiController('/prefix')]` for API handlers.
- Always provide a `name:` argument — it is used for URI generation.
- The `AttributeRouteLoader` scans `Application\Handler` recursively; there is nothing else to register.
- If requested, routes can be registered manually in `config/routes.php` instead of using attributes.

**Injection pattern:** dependencies are resolved by League Container via constructor injection (ReflectionContainer autowiring applies).
For common PSR dependencies, use `Awareness` traits instead of constructor injection — see below.

---

## Dependency Injection — League Container

`config/container.php` wires the application. Structure:
1. `Container` is created with `defaultToShared: true` and a `ReflectionContainer` delegate.
2. Built-in service providers are registered in a fixed order.
3. Application-specific bindings (interface → implementation) are added directly.
4. `afterResolve` hooks inject *Aware* dependencies after resolution.

**Adding a new service:**
- If the service is standalone and simple, add it directly in `config/container.php`.
- If the service has several related bindings or needs a boot hook, create a `ServiceProvider` in `src/Application/ServiceProvider` that extends `AbstractServiceProvider`.
- If the provider needs to register `afterResolve` hooks, also implement `BootableServiceProviderInterface` and use `boot()`.

**Service Provider template:**

```php
<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;

class MyServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            MyInterface::class,
        ]);
    }

    public function register(): void
    {
        $this->getContainer()
            ->add(MyInterface::class, MyImplementation::class);
    }
}

```

---

## Awareness pattern

`debuss-a/awareness` provides interface+trait pairs to inject PSR dependencies without constructor bloat.  
`afterResolve` hooks in service providers (or the container) call the setters automatically after resolution.

Available interfaces (all auto-injected via existing hooks):

| Interface | Setter | Injected by |
|---|---|---|
| `LoggerAwareInterface` (PSR-3) | `setLogger()` | `LoggerServiceProvider` |
| `ResponseFactoryAwareInterface` | `setResponseFactory()` | `HttpFactoryServiceProvider` |
| `RequestFactoryAwareInterface` | `setRequestFactory()` | `HttpFactoryServiceProvider` |
| `StreamFactoryAwareInterface` | `setStreamFactory()` | `HttpFactoryServiceProvider` |
| `ServerRequestFactoryAwareInterface` | `setServerRequestFactory()` | `HttpFactoryServiceProvider` |
| `CacheItemPoolAwareInterface` | `setCacheItemPool()` | `CacheServiceProvider` |
| `CacheAwareInterface` | `setCache()` | `CacheServiceProvider` |
| `ContainerAwareInterface` | `setContainer()` | `config/container.php` |

Usage pattern:

```php
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Awareness\{RequestFactoryAwareInterface, RequestFactoryAwareTrait};

class MyHandler implements RequestHandlerInterface, LoggerAwareInterface, RequestFactoryAwareInterface
{
    use LoggerAwareTrait;
    use RequestFactoryAwareTrait;

    // No constructor needed for these — they are injected automatically.
}
```

**Note:** Not all PSR interfaces available in `Awareness` are auto-injected. Only the one needed by this application
are. You can add missing awareness hooks in the corresponding service provider if needed.

---

## Domain models

Domain models are `final readonly` classes, implementing `JsonSerializable` if expected to be returned as JSON.  
They expose a `static fromArray(array $data): self` named constructor to ease the mapping from data retrieved in the
Infrastructure layer but can be omitted if not needed.  
Properties are public and immutable. Never add setters.

```php
<?php declare(strict_types=1);

namespace Domain\Post;

use JsonSerializable;

final readonly class Post implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $title,
        public string $body,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:     (int)$data['id'],
            userId: (int)$data['userId'],
            title:  $data['title'],
            body:   $data['body'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'     => $this->id,
            'userId' => $this->userId,
            'title'  => $this->title,
            'body'   => $this->body,
        ];
    }
}
```

---

## Domain interfaces

Client interfaces (for external data sources) live in the Domain and return Domain types.  
They must not reference `Application` or `Infrastructure` classes.

```php
<?php declare(strict_types=1);

namespace Domain\Post;

interface PostClientInterface
{
    /** @return Post[] */
    public function getAll(): array;

    public function getById(int $id): Post;
}
```

---

## Exceptions

Domain and Infrastructure exceptions are plain `RuntimeException`s — they must not know about HTTP, status codes, or
`ProblemDetailsExceptionInterface`. Mapping an exception to an HTTP response is an `Application` (delivery) concern,
handled by `ExceptionStatusMapper` — see [Error handling](#error-handling) below.

- `Domain\Shared\Exception\NotFoundException` — thrown when a requested resource does not exist. Reusable across
  domains; exposes `static create(string $resource, int|string $id): self`.
- `Infrastructure\Shared\Exception\ClientException` — thrown by Infrastructure HTTP clients when an upstream call
  fails (network error, non-2xx response, ...). Exposes `static create(string $message, ?Throwable $previous = null): self`.

**When to add a domain-specific exception:**  
If the exception semantics are specific to one domain, create it in `Domain\{Context}\Exception`.  
If it can be reused across domains, put it in `Domain\Shared\Exception` (or `Infrastructure\Shared\Exception` for
infrastructure-only failures with no domain meaning).

**When adding a new exception that should map to a non-500 status:** register it in
`ExceptionStatusMapper`'s `$map` (`src/Application/Http/ProblemDetails/ExceptionStatusMapper.php`) — do not add HTTP
concerns back onto the exception class itself.

---

## Infrastructure clients

Infrastructure classes implement their corresponding Domain interface.  
They use `RequestFactoryAwareTrait` for building PSR-7 requests — never inject `RequestFactoryInterface` in the constructor.

```php
<?php declare(strict_types=1);

namespace Infrastructure\Post;

use Awareness\{RequestFactoryAwareInterface, RequestFactoryAwareTrait};
use Domain\Post\{Post, PostClientInterface};
use Domain\Shared\Exception\NotFoundException;
use Infrastructure\Shared\Exception\ClientException;
use Psr\Http\Client\{ClientExceptionInterface, ClientInterface};

class PostClient implements PostClientInterface, RequestFactoryAwareInterface
{
    use RequestFactoryAwareTrait;

    private const string URL = 'https://jsonplaceholder.typicode.com/posts';

    public function __construct(
        private readonly ClientInterface $client,
    ) {}

    public function getById(int $id): Post
    {
        $request = $this->requestFactory->createRequest('GET', self::URL . '/' . $id);

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw ClientException::create($e->getMessage(), previous: $e);
        }

        if ($response->getStatusCode() === 404) {
            throw NotFoundException::create('Post', $id);
        }

        $response->getBody()->rewind();

        return Post::fromArray(json_decode($response->getBody()->getContents(), true));
    }
}
```

Bind the interface to the implementation in `config/container.php`:

```php
$container->add(PostClientInterface::class, PostClient::class);
```

---

## Error handling

- **HTML errors**: `Whoops` with `PrettyPageHandler` in development, `PlainTextHandler` in production. Controlled by `APP_ENV`.
- **API errors**: `ProblemDetailsMiddleware` is scoped to `/api` in the pipeline. It uses `MappingProblemDetailsResponseFactory`
  (`src/Application/Http/ProblemDetails/MappingProblemDetailsResponseFactory.php`), which consults `ExceptionStatusMapper`
  to turn a plain Domain/Infrastructure exception into the right status/title, then falls back to the stock
  `ProblemDetailsResponseFactory` behavior (500, generic detail) for anything unmapped. Only reach for
  `ProblemDetailsExceptionInterface` directly if an exception needs response fields the mapper can't express
  (e.g. per-instance `additional` data) — the mapper is the default path.
- **Logging**: Both `ErrorHandler` and `ProblemDetailsMiddleware` attach a listener that calls `$logger->error(...)` with full request/response context.

Do not wrap handler logic in `try/catch` to build HTTP responses — throw `NotFoundException` or `ClientException` from the domain/infrastructure layer and let the middleware pipeline convert them.

---

## Configuration

Configuration is loaded by `borschphp/config` via `ConfigurationServiceProvider`.  
Inject `Config $config` in any constructor — it is autowired:

```php
$config->get('APP_ENV');
$config->getOrDefault('APP_URL', 'http://localhost:8080');
```

Environment variables are declared in `.env` (copy from `.env.example`).  
In production, set `APP_ENV=production` as a real server environment variable rather than relying on `.env`.

Other configuration source are available in `borschphp/config`:
- ini files
- JSON files
- YAML files
- PHP files returning arrays

---

## Helper functions

Globally available (loaded via `bootstrap/helpers.php`):

| Function | Returns |
|---|---|
| `app_path(string ...$paths)` | `{root}/{path}` |
| `source_path(string ...$paths)` | `{root}/src/{path}` |
| `config_path(string ...$paths)` | `{root}/config/{path}` |
| `storage_path(string ...$paths)` | `{root}/storage/{path}` |
| `cache_path(string ...$paths)` | `{root}/storage/cache/{path}` |
| `logs_path(string ...$paths)` | `{root}/storage/logs/{path}` |

---

## Middleware pipeline rules

- Middleware order in `config/pipeline.php` is significant — never reorder without understanding side effects.
- Middlewares are executed in the order they are declared, and the request flows from top to bottom.
- `ErrorHandler` must always be **first** (outermost).
- `ProblemDetailsMiddleware` is scoped to `/api` — do not move it to the global scope.
- Additional middleware should be path-scoped where possible.

---

## Testing

Pest is the test framework (`pestphp/pest`). Tests live in `tests/`.

```bash
./vendor/bin/pest
```

---

## Do not do

- Do not omit `declare(strict_types=1)` from any PHP source file in `src/`.
- Do not import `Infrastructure` classes directly inside `Application` handlers — always depend on Domain interfaces.
- Do not use `Logger::class` directly where `LoggerInterface::class` is sufficient.
- Do not resolve container entries eagerly inside service provider `register()` — prefer lazy `addArgument()` chains.
- Do not suppress exceptions with empty `catch` blocks (health check probes are the only accepted exception).
- Do not add a route to `config/routes.php` for a handler that already declares routing attributes — it will throw an exception.
- Do not place domain types or shared concepts in the `Application` namespace.

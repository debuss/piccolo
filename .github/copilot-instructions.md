# Copilot Instructions

Piccolo is a PHP 8.4+ application skeleton built on Laminas Stratigility, Mezzio Router, and League Container.
It follows Domain-Driven Design conventions and PSR standards throughout (PSR-3, PSR-7, PSR-11, PSR-15, PSR-17, PSR-18).

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
- `Application` may depend on `Domain` (interfaces, models, exceptions); it must not use `Infrastructure` classes at all.
- Infrastructure implementations are only wired to Domain interfaces in `config/container.php` (the composition root).
- These rules are enforced by the architecture tests in `tests/ArchitectureTest.php`.
- Shared cross-domain concepts (e.g. `PageResult`, `NotFoundException`) belong in `Domain\Shared`, not in any specific domain namespace.

---

## PHP conventions

- Every PHP file must start with `<?php declare(strict_types=1);`.
- Use `final readonly class` for domain value objects and models.
- Use `readonly class` for infrastructure classes and middleware that are not extended. Handlers extending the base
  `Handler` class cannot be readonly (see [Handlers](#handlers)).
- Constructor property promotion is the standard; never declare properties separately unless unavoidable.
- Always use named arguments for clarity when calling factory methods with multiple parameters.
- Prefer `match` over `switch`. Prefer early returns over nested `if` blocks.
- Never suppress exceptions silently.

---

## Handlers

Handlers implement `Psr\Http\Server\RequestHandlerInterface` and live in `src/Application/Handler`.  
They are organized in sub-namespaces by concern (e.g. `Application\Handler\Api`, `Application\Handler\HealthCheck`, `Application\Handler\OpenApi`).

Handlers extend `Application\Handler\Handler`, which builds responses with the PSR-17 factories (injected through the
Awareness pattern): use `$this->json($data, $status)` and `$this->html($content, $status)`, or
`$this->responseFactory` / `$this->streamFactory` for other responses.
Never instantiate response classes of a PSR-7 implementation (e.g. `Laminas\Diactoros\Response\JsonResponse`): the
implementation is only chosen in `config/container.php`.

**Routing is attribute-based.** Always declare routes with attributes:

```php
use Routing\Attribute\{AsController, Get, Post};

#[AsController]            // for web routes — no path prefix
#[AsController('/api/v1')] // for API routes — applies a prefix to all methods inside

#[Get('/posts[/{id:\d+}]', name: 'api.v1.posts.get')]
#[Post('/posts', name: 'api.v1.posts.create')]
```

- Every handler with route attributes must have `#[AsController]` (or a custom attribute extending it), otherwise its routes are not loaded.
- Use `#[AsController]` for HTML/generic handlers and `#[AsController('/prefix')]` for API handlers.
- Route attributes (`Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`, `Route`) must be on public methods of concrete classes.
- If a short attribute name conflicts with an application class, alias the namespace: `use Routing\Attribute as Http;` then `#[Http\Options(...)]`.
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
| `UriFactoryAwareInterface` | `setUriFactory()` | `HttpFactoryServiceProvider` |
| `UploadedFileFactoryAwareInterface` | `setUploadedFileFactory()` | `HttpFactoryServiceProvider` |
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

use Domain\Shared\Exception\NotFoundException;

interface PostClientInterface
{
    /** @return Post[] */
    public function getAll(): array;

    /** @throws NotFoundException When no post exists with this id */
    public function getById(int $id): Post;
}
```

Document the Domain exceptions a method can throw with `@throws`: they are part of the contract the `Application`
layer relies on.

---

## Exceptions

Domain and Infrastructure exceptions are plain `RuntimeException`s — they must not know about HTTP, status codes, or
`ProblemDetailsExceptionInterface`. Translating an exception into an HTTP response is an `Application` (delivery)
concern, done by the handler that knows what the exception means — see [Error handling](#error-handling) below.

- `Domain\Shared\Exception\NotFoundException` — thrown when a requested resource does not exist. Reusable across
  domains; exposes `static create(string $resource, int|string $id): self`.
- `Infrastructure\Shared\Exception\ClientException` — thrown by Infrastructure HTTP clients when an upstream call
  fails (network error, non-2xx response, ...). Exposes `static create(string $message, ?Throwable $previous = null): self`.

**When to add a domain-specific exception:**  
If the exception semantics are specific to one domain, create it in `Domain\{Context}\Exception`.  
If it can be reused across domains, put it in `Domain\Shared\Exception` (or `Infrastructure\Shared\Exception` for
infrastructure-only failures with no domain meaning).

**When a new exception should result in a non-500 status:** catch it in the handler and return the matching
response — do not add HTTP concerns back onto the exception class itself.

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

- **HTML errors**: Stratigility `ErrorHandler` with its `ErrorResponseGenerator`: the exception (message and trace) is
  displayed outside production, only the reason phrase in production. Controlled by `APP_ENV`.
- **API errors**: `ProblemDetailsMiddleware` is scoped to `/api` in the pipeline and uses the stock
  `ProblemDetailsResponseFactory`: any exception becomes a generic 500, with the exception details only outside
  production.
- **Domain exceptions**: the handler catches the Domain exceptions it can translate into a meaningful response and
  returns it with the `ProblemDetailsResponseFactory` (injected in the constructor), e.g. in `PostHandler`:

  ```php
  try {
      return $this->json($this->client->getById((int)$id));
  } catch (NotFoundException $e) {
      return $this->problemDetails->createResponse($request, 404, $e->getMessage());
  }
  ```

  Only catch the exceptions the handler knows the meaning of, let everything else bubble up to the middleware.
  Never catch `Infrastructure` exceptions in `Application` (e.g. `ClientException`): they become a generic 500.
- **Logging**: `ErrorHandler` and `ProblemDetailsMiddleware` share `Application\Http\ErrorLogListener`, which only logs
  the request method, the original URI and the response status. Never log request headers, cookies or body: they can
  contain credentials (Authorization header, session cookie, password, ...).
- **Server request creation errors** (invalid header, malformed uploaded files, ...) happen before the pipeline and are
  handled by `Application\Http\ServerRequestErrorResponseGenerator` (logged, 400 response).

---

## Configuration

Configuration is loaded once by `bootstrap/app.php` from `config/configuration.php` (`borschphp/config`), then added
to the container. Sources are merged in order, the last one wins:

1. the `.env` file, optional (copy from `.env.example`)
2. the real environment variables

Inject `Config $config` in any constructor — it is autowired:

```php
$config->getOrDefault('APP_URL', 'http://localhost:8080');
```

**Never read `APP_ENV` directly.** Depend on the `Application\Environment` enum (also autowired) instead:

```php
public function __construct(private Environment $environment) {}

if ($this->environment->isProduction()) { /* ... */ }
```

`bootstrap/app.php` is also where the PHP runtime settings depending on the configuration are applied
(`display_errors`, timezone from `TIMEZONE`).  
In production, set `APP_ENV=production` as a real server environment variable rather than relying on `.env`.

Logging is configured with environment variables: `LOG_STREAM` (`php://stderr` by default, or a file path relative to
the app root) and `LOG_LEVEL` (`debug` by default, `info` in production).

Other configuration sources are available in `borschphp/config` (add them in `config/configuration.php`):
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
- `ErrorHandler` must always be **first** (outermost), followed by `OriginalMessages` (keeps the original URI, as
  middleware piped on a path only see the URI without that path prefix).
- `ProblemDetailsMiddleware` is scoped to `/api` — do not move it to the global scope.
- Additional middleware should be path-scoped where possible.

---

## Testing

Pest is the test framework (`pestphp/pest`). Tests live in `tests/`, unit tests mirror the `src/` structure in
`tests/Unit`, and `tests/ArchitectureTest.php` enforces the layer boundaries.

```bash
./vendor/bin/pest
```

---

## Do not do

- Do not omit `declare(strict_types=1)` from any PHP source file in `src/`.
- Do not import `Infrastructure` classes anywhere in `Application` — always depend on Domain interfaces.
- Do not use classes of a specific PSR-7 implementation (e.g. Diactoros) outside `config/container.php` — use the PSR-17
  factories.
- Do not log request headers, cookies or body.
- Do not use `Logger::class` directly where `LoggerInterface::class` is sufficient.
- Do not resolve container entries eagerly inside service provider `register()` — prefer lazy `addArgument()` chains.
- Do not suppress exceptions with empty `catch` blocks.
- Do not add a route to `config/routes.php` for a handler that already declares routing attributes — it will throw an exception.
- Do not place domain types or shared concepts in the `Application` namespace.

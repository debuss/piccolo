# Copilot Instructions

Piccolo is a PHP 8.4+ application skeleton built on the Piccolo framework (`debuss-a/piccolo-framework`: Laminas
Stratigility, Mezzio Router) and its scoped container (`debuss-a/piccolo-container`). It runs on a classic PHP SAPI
as well as in FrankenPHP worker mode.
It follows Domain-Driven Design conventions and PSR standards throughout (PSR-3, PSR-7, PSR-11, PSR-15, PSR-17, PSR-18).

Read these instructions before suggesting or generating any code.

---

## Architecture

### Layer boundaries

```
src/
├── Application/   # HTTP delivery: handlers, middleware, console commands, service providers
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
- `Domain` must not depend on the framework (`Piccolo\...`) either.

### Framework

The generic code lives in the `debuss-a/piccolo-framework` package (namespace `Piccolo\`), not in the skeleton:
`Piccolo\Kernel` (boot), `Piccolo\Application`, `Piccolo\Environment`, `Piccolo\Http\Handler` (base class of the
handlers), the middlewares (`Piccolo\Middleware\*`), the service providers (`Piccolo\ServiceProvider\*`), the runners
(`Piccolo\Runtime\*`) and the console commands `cache:clear`, `cache:warmup`, `routes:list` (`Piccolo\Console\*`).
Never copy these classes into `src/`: configure or replace them from `config/container.php`.

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

Handlers are `Psr\Http\Server\RequestHandlerInterface` and live in `src/Application/Handler`.  
They are organized in sub-namespaces by concern (e.g. `Application\Handler\Api`, `Application\Handler\HealthCheck`, `Application\Handler\OpenApi`).

Handlers extend the abstract `Piccolo\Http\Handler` of the framework (which implements `RequestHandlerInterface`, so only
`handle()` has to be written). It builds responses with the PSR-17 factories (injected through the Awareness pattern):
use `$this->json($data, $status)` and `$this->html($content, $status)`, or `$this->responseFactory` /
`$this->streamFactory` for other responses. `json()` lets `JsonException` bubble up when the data cannot be encoded.
In unit tests, set the factories with `setResponseFactory()` / `setStreamFactory()`, as the container does.
Never instantiate response classes of a PSR-7 implementation (e.g. `Laminas\Diactoros\Response\JsonResponse`): the
implementation is only chosen by the `HttpFactoryServiceProvider` of the framework (replaceable in `config/container.php`).

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

**Injection pattern:** dependencies are resolved by the container via constructor injection (autowiring applies; handlers are transient).
For common PSR dependencies, use `Awareness` traits instead of constructor injection — see below.

---

## Dependency Injection — Piccolo Container

`config/container.php` is the composition root of the application: it returns
`static function (ContainerBuilder $builder, Config $config, Environment $environment): void`, called by the Kernel
after the service providers of the framework, so its definitions can replace them.

Every service has a **lifetime**:

| Lifetime | Method | Use for |
|---|---|---|
| Singleton | `$builder->singleton()` | stateless services, configuration, clients, factories |
| Scoped | `$builder->scoped()` | services with a per-request state (unit of work, current user, transaction) |
| Transient | `$builder->transient()` | light services that need a new instance each time |

- The classes that are not registered (handlers, middlewares, ...) are autowired as **transient** services.
- Each request is handled in its own scope, disposed at the end of the request (`Piccolo\Container\Disposable`).
- A scoped service cannot be resolved from the root container, nor be a dependency of a singleton: a `ScopeException`
  is thrown.
- In FrankenPHP worker mode, the singletons live for all the requests of the worker: **never keep a request state in a
  singleton**. Make the service scoped, or reset it after each request (`Piccolo\Container\Resettable`, or
  `$builder->onReset()`).

```php
$builder->singleton(PostClientInterface::class, PostClient::class);          // interface → implementation
$builder->singleton(Foo::class, static fn (Config $config) => new Foo(...));  // factory, its parameters are autowired
$builder->scoped(UnitOfWork::class);
$builder->alias(BarInterface::class, Bar::class);
$builder->inflect(FooAwareInterface::class, static fn (FooAwareInterface $service, ContainerInterface $container) =>
    $service->setFoo($container->get(Foo::class)));
```

**Adding a new service:**
- If the service is standalone and simple, add it directly in `config/container.php`.
- If the service has several related definitions, create a service provider in `src/Application/ServiceProvider`
  implementing `Piccolo\Container\ServiceProvider`, and add it with `$builder->addProvider()` in `config/container.php`.

**Service Provider template:**

```php
<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Piccolo\Container\{ContainerBuilder, ServiceProvider};

class MyServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(MyInterface::class, MyImplementation::class);
    }
}
```

---

## Awareness pattern

`debuss-a/awareness` provides interface+trait pairs to inject PSR dependencies without constructor bloat.  
Inflectors (`$builder->inflect()`) in the service providers of the framework call the setters automatically after
the creation of the service.

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
| `ContainerAwareInterface` | `setContainer()` (the scope of the request, or the root container) | `ApplicationServiceProvider` |

Usage pattern:

```php
use Piccolo\Http\Handler;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Awareness\{RequestFactoryAwareInterface, RequestFactoryAwareTrait};

class MyHandler extends Handler implements LoggerAwareInterface, RequestFactoryAwareInterface
{
    use LoggerAwareTrait;
    use RequestFactoryAwareTrait;

    // No constructor needed for these — they are injected automatically.
}
```

**Note:** Not all PSR interfaces available in `Awareness` are auto-injected. Only the one needed by this application
are. You can add missing inflectors in `config/container.php` if needed.

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
$builder->singleton(PostClientInterface::class, PostClient::class);
```

Clients are usually singletons: they must not keep a state of a request (see the lifetimes in
[Dependency Injection](#dependency-injection--piccolo-container)).

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
- **Logging**: `ErrorHandler` and `ProblemDetailsMiddleware` share `Piccolo\Http\ErrorLogListener`, which only logs
  the request method, the original URI and the response status. Never log request headers, cookies or body: they can
  contain credentials (Authorization header, session cookie, password, ...).
- **Server request creation errors** (invalid header, malformed uploaded files, ...) happen before the pipeline, when
  `Debuss\ServerRequestFactory\ServerRequestFactory` builds the request from the globals, and are handled by
  `Piccolo\Http\ServerRequestErrorResponseGenerator`: 400 response and warning log for a malformed client request
  (`BadRequestException`), 500 response and error log for anything else (configuration, uploaded files, ...).

---

## Configuration

Configuration is loaded once by `Piccolo\Kernel::boot()` from `config/configuration.php` (`borschphp/config`), then added
to the container. Sources are merged in order, the last one wins:

1. the `.env` file, optional (copy from `.env.example`)
2. the real environment variables

Inject `Config $config` in any constructor — it is autowired:

```php
$config->getOrDefault('LOGGER_NAME', 'app');
```

**Never read `APP_ENV` directly.** Depend on the `Piccolo\Environment` enum (also autowired) instead:

```php
public function __construct(private Environment $environment) {}

if ($this->environment->isProduction()) { /* ... */ }
```

`Piccolo\Kernel::boot()` is also where the PHP runtime settings depending on the configuration are applied
(`display_errors`, timezone from `TIMEZONE`).  
In production, set `APP_ENV=production` as a real server environment variable rather than relying on `.env`.

Logging is configured with environment variables: `LOG_STREAM` (`php://stderr` by default, or a file path relative to
the app root) and `LOG_LEVEL` (`debug` by default, `info` in production).

The records logged while handling a request have its identifier in `extra.request_id` (`RequestIdProcessor`, set by
`RequestIdMiddleware` from the `X-Request-Id` header): do not add it to the context of the log calls.

CORS is configured with the `CORS_*` environment variables (see `.env.example` and `CorsServiceProvider`), and disabled
when `CORS_ALLOWED_ORIGINS` is empty.

Other configuration sources are available in `borschphp/config` (add them in `config/configuration.php`):
- ini files
- JSON files
- YAML files
- PHP files returning arrays

---

## Helper functions

Globally available (provided by the framework, from the root directory given to `Kernel::boot()`, see `Piccolo\Paths`):

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
- `RequestIdMiddleware` must always be **first** (outermost), so that errors are logged with the request identifier,
  followed by `ErrorHandler`, then `OriginalMessages` (keeps the original URI, as middleware piped on a path only see
  the URI without that path prefix).
- `ProblemDetailsMiddleware` is scoped to `/api` — do not move it to the global scope.
- `CorsMiddleware` (mezzio-cors) is piped globally, before `RouteMiddleware`, and only when CORS is enabled
  (`CORS_ALLOWED_ORIGINS`) — do not scope it to a path, it matches the routes on its own with the full URI.
- Additional middleware should be path-scoped where possible.

---

## Console commands

- `bin/console` runs Symfony Console with the container built by `Piccolo\Kernel`, the commands being resolved from a
  scope.
- Commands live in `src/Application/Console`, extend `Symfony\Component\Console\Command\Command` and declare their name
  and description with `#[AsCommand]`.
- Map each command name to its class in the array returned by `config/commands.php` (the commands of the framework are
  already registered): commands are resolved lazily from the container, so inject their dependencies through the
  constructor.
- The production caches (routes) are regenerated by `bin/console cache:warmup`, to run on each deployment.

---

## Testing

Pest is the test framework (`pestphp/pest`). Tests live in `tests/`, unit tests mirror the `src/` structure in
`tests/Unit`, and `tests/ArchitectureTest.php` enforces the layer boundaries.

End-to-end tests go through the real kernel, container, pipeline and routes: `httpApplication()` (defined in
`tests/Pest.php`) returns `Kernel::boot(...)->http()`, to call `->handle(new ServerRequest(...))` on (see
`tests/Feature/HttpTest.php`). The classes of the framework are tested in its own package, not here.

```bash
./vendor/bin/pest
```

---

## Do not do

- Do not omit `declare(strict_types=1)` from any PHP source file in `src/`.
- Do not import `Infrastructure` classes anywhere in `Application` — always depend on Domain interfaces.
- Do not use classes of a specific PSR-7 implementation (e.g. Diactoros) in `src/` — use the PSR-17 factories (the
  implementation is chosen by `HttpFactoryServiceProvider`, replaceable in `config/container.php`).
- Do not log request headers, cookies or body.
- Do not use `Logger::class` directly where `LoggerInterface::class` is sufficient.
- Do not resolve container entries eagerly inside service provider `register()` — use factory closures, their
  parameters are resolved when the service is first needed.
- Do not keep a request state (current user, request identifier, ...) in a singleton — use a scoped service.
- Do not copy the classes of the framework into `src/`.
- Do not suppress exceptions with empty `catch` blocks.
- Do not add a route to `config/routes.php` for a handler that already declares routing attributes — it will throw an exception.
- Do not place domain types or shared concepts in the `Application` namespace.

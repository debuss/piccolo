# Piccolo

Piccolo is a lightweight PHP skeleton inspired by the Mezzio Skeleton App, designed to keep the same PSR-first architecture with less bootstrap complexity.

It uses Laminas and Mezzio components directly (without `mezzio/mezzio`), through the
[Piccolo framework](https://github.com/debuss/piccolo-framework) package: attribute-based routing, service-provider-driven
dependency injection with a [scoped container](https://github.com/debuss/piccolo-container) (singleton, scoped and
transient services, as in .NET), and support for the [FrankenPHP worker mode](#worker-mode-frankenphp).

The skeleton only contains your application: handlers, domain, infrastructure, and the configuration of the framework.

## Goals

- Keep a **simple starting point** for modern PSR-7 / PSR-15 applications
- Preserve strong architectural boundaries (**Application / Domain / Infrastructure**)
- Reduce boilerplate around container wiring and route registration
- Stay framework-agnostic enough to evolve with your project

## Tech Stack

- **Framework**
  - `debuss-a/piccolo-framework`: kernel, application, runners, service providers, middlewares, console commands
  - `debuss-a/piccolo-container`: PSR-11 container with singleton, scoped and transient lifetimes
- **Runtime**
  - PHP 8.4+, classic SAPI (PHP-FPM, built-in server, ...) or FrankenPHP worker mode
  - `laminas/laminas-stratigility`, `laminas/laminas-httphandlerrunner`
  - `laminas/laminas-diactoros` (default PSR-7 / PSR-17 implementation, swappable)
  - `debuss-a/server-request-factory` (server request from the globals, with any PSR-17 implementation)
- **Routing**
  - `mezzio/mezzio-fastroute`
  - `debuss-a/attribute-routing` (attribute route collector)
- **Configuration**
  - `borschphp/config` (with `.env` support)
- **Templating**
  - `mezzio/mezzio-platesrenderer` (Plates)
- **Error handling / API errors**
  - `laminas/laminas-stratigility` error handler (exception details outside production only)
  - `mezzio/mezzio-problem-details` (RFC 7807)
- **Logging**
  - `monolog/monolog`
- **HTTP client example**
  - `php-http/curl-client`

## Getting Started

```bash
composer create-project debuss-a/piccolo my-app   # also creates the .env file from .env.example
cd my-app
composer serve
```

From a clone of this repository, run `composer install` and `cp .env.example .env` instead.

Application runs at: `http://localhost:8080`

## Project Structure

```text
.
├── bin/
│   └── console          # console entry point: Kernel::boot(...)->console()->run()
├── config/
│   ├── commands.php     # console commands of the application
│   ├── configuration.php # configuration sources (.env, environment variables)
│   ├── container.php    # services of the application (the composition root)
│   ├── pipeline.php     # middleware pipeline
│   └── routes.php       # attribute route loader + manual routes
├── public/
│   └── index.php        # front controller: Kernel::boot(...)->http()->run()
├── src/
│   ├── Application/     # delivery layer: handlers (and your middlewares, commands, service providers)
│   ├── Domain/          # business contracts/models/shared concepts
│   └── Infrastructure/  # external integrations (HTTP client, persistence...)
├── storage/
│   ├── cache/
│   ├── logs/
│   ├── openapi.yaml
│   └── templates/
├── tests/               # Pest: architecture, end-to-end (Feature) and unit tests
└── Dockerfile           # FrankenPHP in worker mode
```

## Core Architecture

### 1) The kernel and the application

`Piccolo\Kernel` (framework) boots the application the same way for HTTP and the console: paths, configuration,
environment and PHP settings, then the container. `Piccolo\Application` mirrors the familiar Mezzio app flow
(`pipe()`, `route()`, `get()`, ...), and handles each request in its own container scope.

### 2) Container with Service Providers

The service providers of the framework register:

- HTTP factories (PSR-17, Laminas Diactoros by default)
- Application, pipeline and runner (classic SAPI or FrankenPHP worker)
- Router (FastRoute) and routing middlewares
- Error handler
- Logger
- Template renderer
- Problem Details middleware
- CORS middleware

`config/container.php` then registers the services of the application, and can replace the ones of the framework.
Every service has a lifetime:

| Lifetime | Instances | Examples |
|---|---|---|
| **Singleton** | one for the whole application | configuration, logger, router, HTTP clients |
| **Scoped** | one per request | unit of work, current user |
| **Transient** | a new one each time | handlers, middlewares (the classes that are not registered are autowired as transient) |

```php
return static function (ContainerBuilder $builder, Config $config, Environment $environment): void
{
    $builder->singleton(PostClientInterface::class, PostClient::class);
    $builder->scoped(UnitOfWork::class);
};
```

The PSR-7 / PSR-17 implementation is only chosen by `HttpFactoryServiceProvider`: register it again in
`config/container.php` with other factories to switch. The rest of the framework and of the skeleton (handlers, server
request creation, error responses) only relies on the PSR-17 interfaces.

The `debuss-a/awareness` package is used to inject common dependencies into *Aware* classes after their creation (e.g. logger, factories, container), which helps keep constructors small.

### 3) Routing strategy

Piccolo supports:

- **Attribute-based routing** in handlers (default)
- Optional manual route registration in `config/routes.php`

Attributes are collected via `AttributeRouteLoader` on `Application\Handler`.

Middlewares specific to some routes are declared on the handler too, with `#[Middleware]` (on the class for all its
routes, or on the method): they are executed after the routing, only when the route is matched. For instance, the
OpenAPI document is cached by the clients thanks to `HttpCacheMiddleware` (`Cache-Control`, `ETag` and
`304 Not Modified`):

```php
#[Get(path: '/openapi[.{format:yml|yaml}]', name: 'api.v1.openapi')]
#[Middleware(HttpCacheMiddleware::class)]
public function handle(ServerRequestInterface $request): ResponseInterface
```

Middlewares that apply to a whole path, like `/api`, are piped in `config/pipeline.php` instead.

### 4) Middleware pipeline

`config/pipeline.php` keeps a Mezzio-style flow:

1. `RequestIdMiddleware` (identifies the request, see [Error Handling and Logging](#error-handling-and-logging))
2. `ErrorHandler`
3. `OriginalMessages` (keeps the original URI for middleware piped on a path)
4. `CorsMiddleware`, only when CORS is enabled (see [CORS](#cors))
5. `/api` scoped middlewares (`ApiAcceptHeaderMiddleware`, `ProblemDetailsMiddleware`, `BodyParamsMiddleware`)
6. `RouteMiddleware`
7. `ImplicitHeadMiddleware`
8. `ImplicitOptionsMiddleware`
9. `MethodNotAllowedMiddleware`
10. `DispatchMiddleware`
11. `NotFoundHandler`

### 5) Layer dependencies

Dependencies always point to the Domain: `Application` and `Infrastructure` may use `Domain`, but the Domain uses
neither of them, and `Application` does not use `Infrastructure`. Infrastructure implementations are only wired to
Domain interfaces in `config/container.php`. The Domain does not use the framework either.

These rules are enforced by architecture tests (`tests/ArchitectureTest.php`).

## Built-in Endpoints

- `GET /` — home page (or JSON fallback)
- `GET /api/ping` — lightweight ping endpoint
- `GET /api/v1/posts` — posts example list (JSONPlaceholder)
- `GET /api/v1/posts/{id}` — post by id
- `GET /api/v1/openapi` (or `/api/v1/openapi.yaml`, `/api/v1/openapi.yml`) — OpenAPI document
- `GET /api/v1/redoc` (or `/api/v1/swagger`) — ReDoc UI

## OpenAPI and ReDoc

The API contract is stored in:

- `storage/openapi.yaml`

The ReDoc handler renders documentation directly from the route-generated OpenAPI URL, so docs stay aligned with your app URLs.

## Configuration

The configuration is loaded once by the kernel from `config/configuration.php`:

1. the `.env` file, optional (for local development)
2. the real environment variables, which override the `.env` file

In production, prefer real environment variables (Apache `SetEnv`, nginx `fastcgi_param`, Docker `ENV`, systemd
`Environment=...`) over a `.env` file.

Available variables (see `.env.example`):

- `APP_ENV` — `development` (default) or `production`
- `LOGGER_NAME`
- `LOG_STREAM` — where logs are written: `php://stderr` (default) or a file path relative to the app root
- `LOG_LEVEL` — minimum level to log: `debug` by default, `info` in production
- `TIMEZONE` — applied to the whole application (default `UTC`)
- `MAX_REQUESTS` — in FrankenPHP worker mode, number of requests after which a worker is restarted (unlimited by default)
- `CORS_ALLOWED_ORIGINS`, `CORS_ALLOWED_HEADERS`, `CORS_EXPOSED_HEADERS`, `CORS_ALLOW_CREDENTIALS`, `CORS_MAX_AGE` —
  see [CORS](#cors)

`APP_ENV` is read in a single place, through the `Piccolo\Environment` enum, which services can depend on.
With `APP_ENV=production`:

- errors are not displayed, and error responses do not contain exception details
- the route definitions read from the handler attributes are cached in `storage/cache/route-definitions.cache.php`
- the FastRoute dispatch data is cached in `storage/cache/routes.cache.php`

These caches are never regenerated on their own: run `bin/console cache:warmup` (with `APP_ENV=production`) on each
deployment, see [Console](#console). Once warmed up, `storage/cache` can be read-only.

## CORS

CORS is handled by [mezzio-cors](https://docs.mezzio.dev/mezzio-cors/), and disabled by default: allow the origins of
your front-ends to enable it.

```dotenv
CORS_ALLOWED_ORIGINS=https://app.example.com,http://localhost:5173
```

- Origins accept `*` wildcards (`https://*.example.com`), and `*` allows any origin. The requests of the other origins
  are rejected with a `403`.
- The allowed HTTP methods are those of the requested route. The allowed request headers are `Content-Type` by default
  (`CORS_ALLOWED_HEADERS`), and no response header is exposed besides the CORS-safelisted ones (`CORS_EXPOSED_HEADERS`,
  e.g. `ETag`).
- Credentials (cookies, `Authorization` header) are not allowed by default (`CORS_ALLOW_CREDENTIALS`), and cannot be
  allowed for any origin.
- Preflight responses are not cached by the browser by default (`CORS_MAX_AGE`, in seconds).

The configuration applies to every route: `CorsMiddleware` is piped before the routing, as it matches the requested
route on its own. Behind a reverse proxy, configure the trusted proxies (register
`Debuss\ServerRequestFactory\ServerRequestFactory` again with a `TrustedProxies` argument in `config/container.php`, see
`ApplicationServiceProvider`) so that the scheme and host of the request, compared to the origin, are the public ones.

## Error Handling and Logging

- **HTML errors**: Stratigility `ErrorHandler`; the exception (message and trace) is only displayed outside
  production, otherwise the reason phrase is returned.
- **API errors**: `ProblemDetailsMiddleware` (scoped to `/api`) returns RFC 7807 responses. Unknown exceptions become a
  generic 500, with details only outside production. Handlers translate the Domain exceptions they know about, e.g.
  `PostHandler` returns a 404 Problem Details response on `NotFoundException`.
- **Logs**: JSON lines written by Monolog to `LOG_STREAM`. Errors are logged with the request method, URI and response
  status only: headers, cookies and body are never logged, as they can contain credentials.
- **Request identifier**: `RequestIdMiddleware` keeps the `X-Request-Id` header set by a reverse proxy or a load
  balancer (letters, digits, `.`, `_`, `:` and `-`, at most 128 characters), or generates one. It is added to every log
  record of the request (`extra.request_id`), set in the `request_id` attribute of the request, and sent back in the
  `X-Request-Id` header of the response, errors included. With CORS, add `X-Request-Id` to `CORS_EXPOSED_HEADERS` for
  a front-end to read it.

## Console

`bin/console` is built on Symfony Console, with the same configuration and container as the web application:

```bash
bin/console                # lists the commands
bin/console routes:list    # lists the routes, with their middlewares and handler
bin/console cache:clear    # removes the content of storage/cache
bin/console cache:warmup   # regenerates the production caches (routes), to run on each deployment
```

The commands of the framework are always available. Add yours in `src/Application/Console`, and map them to their name
in the array returned by `config/commands.php`. They are resolved from the container only when they are run, so they
can depend on any service.

## Worker mode (FrankenPHP)

In [worker mode](https://frankenphp.dev/docs/worker/), FrankenPHP boots the application once (container, pipeline,
routes) and keeps it in memory to handle the requests, instead of booting it again for each request. Nothing changes in
the code: `public/index.php` detects the worker and handles the requests in a loop.

```bash
docker build -t piccolo .
docker run -p 80:80 -p 443:443 -e SERVER_NAME=localhost piccolo
```

- Each request is handled in its own container scope: the scoped services are created again for each request.
- After each request, the singletons holding a state are reset (Monolog buffers, request identifier, ...), and the
  worker is restarted after `MAX_REQUESTS` requests (500 in the `Dockerfile`).
- **Never keep a request state in a singleton** (the current user, ...): it would be seen by the next requests. Make
  the service scoped, or reset it after each request (implement `Piccolo\Container\Resettable`).
- The `Dockerfile` runs `bin/console cache:warmup` (`APP_ENV=production`), so the routes are compiled at build time.

## Testing

Pest is included as a dev dependency:

```bash
composer test
```

- `tests/ArchitectureTest.php` enforces the [layer dependencies](#5-layer-dependencies).
- `tests/Feature/HttpTest.php` sends requests through the real kernel, container, pipeline and routes, with the
  `httpApplication()` helper of `tests/Pest.php`.
- The classes of the framework and of the container are tested in their own packages.

## Extending the Skeleton

- Add new handlers in `src/Application/Handler`
- Declare routes via attributes (or manually in `config/routes.php`)
- Register app services in `config/container.php` or dedicated service providers, with the right lifetime
- Add console commands in `src/Application/Console`, and map them in `config/commands.php`
- Keep business logic in `Domain`, and external IO in `Infrastructure`

---

Piccolo is intentionally pragmatic: small enough to start quickly, structured enough to scale cleanly.

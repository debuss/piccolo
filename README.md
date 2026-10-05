# Piccolo

Piccolo is a lightweight PHP skeleton inspired by the Mezzio Skeleton App, designed to keep the same PSR-first architecture with less bootstrap complexity.

It uses Laminas and Mezzio components directly (without `mezzio/mezzio`) and provides a clean, editable `Application` layer, attribute-based routing, and service-provider-driven dependency injection.

## Goals

- Keep a **simple starting point** for modern PSR-7 / PSR-15 applications
- Preserve strong architectural boundaries (**Application / Domain / Infrastructure**)
- Reduce boilerplate around container wiring and route registration
- Stay framework-agnostic enough to evolve with your project

## Tech Stack

- **Runtime**
  - PHP 8.4+
  - `laminas/laminas-stratigility`
  - `laminas/laminas-httphandlerrunner`
  - `laminas/laminas-diactoros` (default PSR-7 / PSR-17 implementation, swappable)
- **Routing**
  - `mezzio/mezzio-fastroute`
  - `debuss-a/attribute-routing` (attribute route collector)
- **Container**
  - `league/container` + Service Providers + ReflectionContainer
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
composer install
cp .env.example .env
composer serve
```

Application runs at: `http://localhost:8080`

## Project Structure

```text
.
├── bootstrap/
│   ├── app.php          # loads the configuration, applies PHP runtime settings, builds the container
│   ├── defines.php      # global defines
│   └── helpers.php      # path helper functions
├── config/
│   ├── configuration.php # configuration sources (.env, environment variables)
│   ├── container.php    # League\Container wiring + service providers
│   ├── pipeline.php     # middleware pipeline
│   └── routes.php       # attribute route loader + manual routes
├── public/
│   └── index.php        # front controller
├── src/
│   ├── Application/     # HTTP layer: handlers, middleware, service providers
│   ├── Domain/          # business contracts/models/shared concepts
│   └── Infrastructure/  # external integrations (HTTP client, persistence...)
└── storage/
    ├── cache/
    ├── logs/
    ├── openapi.yaml
    └── templates/
```

## Core Architecture

### 1) Custom `Application` class

The skeleton includes a rewritten `Application` class in `src/Application/Application.php`.  
It mirrors the familiar Mezzio app flow while keeping the entry point fully editable for teams.

### 2) Container with Service Providers

`config/container.php` uses `league/container` and registers focused service providers:

- HTTP factories (PSR-17)
- Request handler runner
- Router (FastRoute)
- Error handler
- Logger
- Template renderer
- Problem Details middleware

The PSR-7 / PSR-17 implementation is only chosen in `config/container.php`, where the factories are given to the
HTTP factories service provider. The rest of the skeleton (handlers, server request creation, error responses) only
relies on the PSR-17 interfaces, so switching to another implementation is a matter of changing these factories.

The `debuss-a/awareness` package is used to inject common dependencies into *Aware* classes after resolution (e.g. logger, factories, container), which helps keep constructors small.

### 3) Routing strategy

Piccolo supports:

- **Attribute-based routing** in handlers (default)
- Optional manual route registration in `config/routes.php`

Attributes are collected via `AttributeRouteLoader` on `Application\Handler`.

### 4) Middleware pipeline

`config/pipeline.php` keeps a Mezzio-style flow:

1. `ErrorHandler`
2. `OriginalMessages` (keeps the original URI for middleware piped on a path)
3. `/api` scoped middlewares (`ApiAcceptHeaderMiddleware`, `ProblemDetailsMiddleware`, `BodyParamsMiddleware`)
4. `RouteMiddleware`
5. `ImplicitHeadMiddleware`
6. `ImplicitOptionsMiddleware`
7. `MethodNotAllowedMiddleware`
8. `DispatchMiddleware`
9. `NotFoundHandler`

### 5) Layer dependencies

Dependencies always point to the Domain: `Application` and `Infrastructure` may use `Domain`, but the Domain uses
neither of them, and `Application` does not use `Infrastructure`. Infrastructure implementations are only wired to
Domain interfaces in `config/container.php`.

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

The configuration is loaded once by `bootstrap/app.php` from `config/configuration.php`:

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

`APP_ENV` is read in a single place, through the `Application\Environment` enum, which services can depend on.
With `APP_ENV=production`:

- errors are not displayed, and error responses do not contain exception details
- the routes are cached in `storage/cache/routes.cache.php`

The route cache is never regenerated on its own: clear `storage/cache` when deploying new routes (not needed when
each deployment starts from a fresh container image).

## Error Handling and Logging

- **HTML errors**: Stratigility `ErrorHandler`; the exception (message and trace) is only displayed outside
  production, otherwise the reason phrase is returned.
- **API errors**: `ProblemDetailsMiddleware` (scoped to `/api`) returns RFC 7807 responses. Unknown exceptions become a
  generic 500, with details only outside production. Handlers translate the Domain exceptions they know about, e.g.
  `PostHandler` returns a 404 Problem Details response on `NotFoundException`.
- **Logs**: JSON lines written by Monolog to `LOG_STREAM`. Errors are logged with the request method, URI and response
  status only: headers, cookies and body are never logged, as they can contain credentials.

## Testing

Pest is included as a dev dependency:

```bash
./vendor/bin/pest
```

## Extending the Skeleton

- Add new handlers in `src/Application/Handler`
- Declare routes via attributes (or manually in `config/routes.php`)
- Register app services in `config/container.php` or dedicated service providers
- Keep business logic in `Domain`, and external IO in `Infrastructure`

---

Piccolo is intentionally pragmatic: small enough to start quickly, structured enough to scale cleanly.

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
  - `laminas/laminas-diactoros`
- **Routing**
  - `mezzio/mezzio-fastroute`
  - `debuss-a/attribute-routing` (attribute route collector)
- **Container**
  - `league/container` + Service Providers + ReflectionContainer
- **Configuration**
  - `borschphp/config` (with `.env` support and optional cache)
- **Templating**
  - `mezzio/mezzio-platesrenderer` (Plates)
- **Error handling / API errors**
  - `filp/whoops`
  - `mezzio/mezzio-problem-details` (RFC 7807)
- **Observability & utilities**
  - `monolog/monolog`
  - `matthiasmullie/scrapbook` (PSR-6 + PSR-16 cache)
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
├── bootstrap/           # global defines + helper path functions
├── config/
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
- Cache
- Database adapter
- Template renderer
- Problem Details middleware
- HTTP client

The `debuss-a/awareness` package is used to inject common dependencies into *Aware* classes after resolution (e.g. logger, factories, container), which helps keep constructors small.

### 3) Routing strategy

Piccolo supports:

- **Attribute-based routing** in handlers (default)
- Optional manual route registration in `config/routes.php`

Attributes are collected via `AttributeRouteLoader` on `Application\Handler`.

### 4) Middleware pipeline

`config/pipeline.php` keeps a Mezzio-style flow:

1. `ErrorHandler`
2. `/api` scoped middlewares (`ProblemDetailsMiddleware`, `BodyParamsMiddleware`)
3. `RouteMiddleware`
4. `ImplicitHeadMiddleware`
5. `ImplicitOptionsMiddleware`
6. `MethodNotAllowedMiddleware`
7. `DispatchMiddleware`
8. `NotFoundHandler`

## Built-in Endpoints

- `GET /` — home page (or JSON fallback)
- `GET /api/ping` — lightweight ping endpoint
- `GET /api/health` — global health check + DB/cache connection status
- `GET /api/v1/posts` — posts example list (JSONPlaceholder)
- `GET /api/v1/posts/{id}` — post by id
- `GET /api/v1/openapi` or `/api/v1/openapi.yaml` — OpenAPI document
- `GET /api/v1/redoc` (or `/api/v1/swagger`) — ReDoc UI

## OpenAPI and ReDoc

The API contract is stored in:

- `storage/openapi.yaml`

The ReDoc handler renders documentation directly from the route-generated OpenAPI URL, so docs stay aligned with your app URLs.

## Configuration

Environment defaults live in `.env.example`:

- `APP_NAME`
- `APP_VERSION`
- `APP_URL`
- `APP_ENV`
- `LOGGER_NAME`
- `TIMEZONE`
- `DB_DRIVER`
- `DB_DATABASE`

Production mode is controlled by `APP_ENV=production` and enables config/router caching behavior where configured.

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

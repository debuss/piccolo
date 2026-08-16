<?php $this->layout('app::layout', ['title' => 'Home']) ?>

<main class="row">
    <div class="bg-body-tertiary p-5">
        <h1>Welcome to Piccolo</h1>
        <p class="lead">
            <a href="https://github.com/debuss/piccolo" target="_blank">Piccolo</a> is a lightweight PHP application
            skeleton designed to help you start quickly with a clean, maintainable foundation.
        </p>
        <p class="lead">
            Inspired by the <strong>Mezzio Skeleton application</strong>, Piccolo keeps the strengths of the Mezzio and
            Laminas ecosystem while reducing framework overhead. It is built on
            <strong>Laminas Stratigility</strong> and <strong>HTTP Handler Runner</strong> to provide a focused
            PSR-15 middleware stack with straightforward conventions and minimal ceremony.
        </p>
    </div>
</main>
<div class="row g-4 p-5 row-cols-1 row-cols-lg-3">
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-bolt"></i> Domain Driven Development
            </h3>
            <p>
                Piccolo adopts a domain-driven structure to keep business rules, delivery logic, and technical concerns
                clearly separated.<br/>
                The default organization is split into three layers: Application, Domain, and Infrastructure.
            </p>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-exchange-alt"></i> HTTP Messages
            </h3>
            <p>
                Piccolo uses
                <a href="https://docs.laminas.dev/laminas-diactoros/v3/usage/" target="_blank">Laminas Diactoros</a> for
                its PSR-7 HTTP message and PSR-17 HTTP factory implementations, providing reliable standards-based
                request and response handling.
            </p>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-dot-circle"></i> Middlewares
            </h3>
            <p>
                The application ships with the core middleware stack from
                <a href="https://docs.laminas.dev/laminas-stratigility/" target="_blank">Laminas Stratigility</a> and
                can be extended easily with custom middleware or reusable PSR-15 components from the broader PHP
                ecosystem.
            </p>
        </div>
    </div>
</div>
<div class="row g-4 px-5 pb-5 row-cols-1 row-cols-lg-3">
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-cube"></i> Containers
            </h3>
            <p>
                Dependency injection is handled through <strong>League Container</strong>, aligned with the
                <a href="https://www.php-fig.org/psr/psr-11/" target="_blank">PSR-11 Container</a> standard and
                structured around service providers for clearer wiring and simpler maintenance.
            </p>
            <?php if (isset($containerName)) : ?>
                    <a href="<?= $this->e($containerDocs) ?>" class="icon-link" target="_blank">
                        Get started with <?= $this->e($containerName) ?>
                        <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                    </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-plane"></i> Routers
            </h3>
            <p>
                Routing is powered by
                <a href="https://docs.mezzio.dev/mezzio/v3/features/router/intro/" target="_blank">Mezzio Router</a>.
                <br/>Piccolo also includes an <strong>attribute-based route loader</strong>, giving you the flexibility
                to declare routes directly in handlers or keep them in dedicated route configuration files.
            </p>
            <?php if (isset($routerName)) : ?>
                <ul>
                    <?php foreach ($routerDocs as $docName => $router) : ?>
                        <li>
                            <a href="<?= $this->e($router) ?>" class="icon-link" target="_blank">
                                <?= $this->e($docName) ?>
                                <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-edit"></i> Templating
            </h3>
            <p>
                Piccolo includes <a href="https://platesphp.com/" target="_blank">League Plates</a> as its default
                template engine.<br/>
                It also relies on
                <a href="https://docs.mezzio.dev/mezzio/v3/features/template/intro/" target="_blank">Mezzio Template Renderer</a>
                so you can switch to another renderer with minimal effort if your project requires it.
            </p>
            <?php if (isset($templateName)) : ?>
                <a href="<?= $this->e($templateDocs) ?>" class="icon-link" target="_blank">
                    Get started with <?= $this->e($templateName) ?>
                    <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                </a>
                <br/>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="row g-4 px-5 pb-5 row-cols-1 row-cols-lg-3">
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-file"></i> Logging
            </h3>
            <p>
                Logging is powered by Monolog, a mature and flexible library that supports structured application
                logging across a wide range of transports and storage targets.
            </p>
            <?php if (isset($loggerName)) : ?>
                <a href="<?= $this->e($loggerDocs) ?>" class="icon-link" target="_blank">
                    Get started with <?= $this->e($loggerName) ?>
                    <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-recycle"></i> Cache
            </h3>
            <p>
                Caching support is provided through
                <a href="https://www.scrapbook.cash/" target="_blank">Scrapbook</a>, with PSR-6 and PSR-16
                implementations and multiple adapters to suit different runtime environments.
            </p>
            <?php if (isset($cacheName)) : ?>
                <a href="<?= $this->e($cacheDocs) ?>" class="icon-link" target="_blank">
                    Get started with <?= $this->e($cacheName) ?>
                    <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-database"></i> Database
            </h3>
            <p>
                Piccolo includes
                <a href="https://docs.laminas.dev/laminas-db/" target="_blank">Laminas DB</a> as a pragmatic default
                for database access.<br/>
                It is intentionally unopinionated here, so you can keep it, replace it, or integrate your preferred
                data access layer as the project evolves.
            </p>
            <?php if (isset($dbName)) : ?>
                <a href="<?= $this->e($dbDocs) ?>" class="icon-link" target="_blank">
                    Get started with <?= $this->e($dbName) ?>
                    <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                </a>
                <br/>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="row g-4 px-5 row-cols-1 row-cols-lg-3">
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-bug"></i> Problem Details
            </h3>
            <p>
                For API error handling, the
                <a href="https://docs.mezzio.dev/mezzio-problem-details/" target="_blank">Mezzio Problem Details</a>
                package offers a consistent way to produce machine-readable error responses aligned with
                <a href="https://tools.ietf.org/html/rfc7807" target="_blank">RFC 7807</a>.
            </p>
            <?php if (isset($pbDetailsName)) : ?>
                <a href="<?= $this->e($pbDetailsDocs) ?>" class="icon-link" target="_blank">
                    Get started with <?= $this->e($pbDetailsName) ?>
                    <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-file-contract"></i> OpenApi Specification
            </h3>
            <p>
                Piccolo includes an OpenAPI specification file that you can adapt to your own API surface.<br/>
                The project deliberately avoids automatic schema generation so your codebase stays focused and free
                from excessive annotation or attribute overhead.
            </p>
            <a href="https://swagger.io/specification/" class="icon-link" target="_blank">
                Get started with OpenApi Specification
                <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
            </a>
        </div>
    </div>
    <div class="col d-flex align-items-start">
        <div>
            <h3 class="fs-2 text-body-emphasis">
                <i class="fa fa-eye"></i> Redoc (Swagger)
            </h3>
            <p>
                A dedicated handler serves a
                <a href="https://redocly.com/redoc" target="_blank">Redoc</a> interface for your API specification,
                making it easy to publish clear, browsable documentation.<br/>
                You can review the bundled example at <a href="/api/v1/redoc">the Rick and Morty API reference</a>.
            </p>
            <a href="https://redocly.com/redoc" class="icon-link" target="_blank">
                Get started with Redoc
                <svg class="bi" aria-hidden="true"><use xlink:href="#chevron-right"></use></svg>
            </a>
        </div>
    </div>
</div>
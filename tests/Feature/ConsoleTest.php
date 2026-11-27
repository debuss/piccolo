<?php declare(strict_types=1);

use Piccolo\Kernel;
use Symfony\Component\Console\Tester\ApplicationTester;

test('lists the routes of config/routes.php, with the middlewares of each route followed by its handler', function () {
    $console = Kernel::boot(dirname(__DIR__, 2))->console();
    $console->setAutoExit(false);

    $tester = new ApplicationTester($console);

    expect($tester->run(['command' => 'routes:list']))->toBe(0)
        ->and($tester->getDisplay())
        // A route from the handler attributes, with its #[Middleware]
        ->toContain('api.v1.openapi')
        ->toContain('Piccolo\Middleware\HttpCacheMiddleware → Application\Handler\OpenApi\OpenApiSpecHandler')
        // A route registered manually
        ->toContain('api.ping')
        ->toContain('Application\Handler\HealthCheck\PingHandler');
});

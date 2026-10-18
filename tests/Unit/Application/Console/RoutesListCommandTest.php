<?php declare(strict_types=1);

use Application\Application;
use Application\Console\RoutesListCommand;
use Application\Environment;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteCollector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fixtures\{FakeContainer, FakeRequestHandlerRunner, FakeRouter};

test('lists the routes of config/routes.php, with the middlewares of each route followed by its handler', function () {
    $container = new FakeContainer([Environment::class => Environment::Development]);
    $application = new Application(
        new MiddlewarePipe(),
        new FakeRequestHandlerRunner(),
        new RouteCollector(new FakeRouter()),
        $container
    );

    $tester = new CommandTester(new RoutesListCommand($application, $container));

    expect($tester->execute([]))->toBe(Command::SUCCESS);

    $display = $tester->getDisplay();
    expect($display)
        // A route from the handler attributes, with its #[Middleware]
        ->toContain('api.v1.openapi')
        ->toContain('Application\Middleware\HttpCacheMiddleware → Application\Handler\OpenApi\OpenApiSpecHandler')
        // A route registered manually
        ->toContain('api.ping')
        ->toContain('Application\Handler\HealthCheck\PingHandler');
});

<?php declare(strict_types=1);

use Application\Application;
use Application\Handler\HealthCheck\HealthHandler;
use Laminas\Diactoros\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Routing\AttributeRouteLoader;

/**
 * FastRoute route configuration
 *
 * Setup routes with a single request method:
 *
 *  $app->get('/', App\Handler\HomePageHandler::class, 'home');
 *  $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 *  $app->put('/album/{id:\d+}', App\Handler\AlbumUpdateHandler::class, 'album.put');
 *  $app->patch('/album/{id:\d+}', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 *  $app->delete('/album/{id:\d+}', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 *  Or with multiple request methods:
 *
 *  $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 *  Or handling all request methods:
 *
 *  $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 *  or:
 *
 *  $app->route(
 *      '/contact',
 *      App\Handler\ContactHandler::class,
 *      Mezzio\Router\Route::HTTP_METHOD_ANY,
 *      'contact'
 *  );
 *
 * @see https://github.com/nikic/FastRoute
 * @see https://docs.mezzio.dev/mezzio/v3/features/router/fast-route/
 */
return static function (Application $app, ContainerInterface $container): void
{
    // For convenience, Piccolo comes with an attribute-based route loader.
    // Which means you can focus on your handlers, defines a few attribute and you will not need to come back here to
    // register your new route. You can define your route and it logic all at the same place (your handler).
    // For more information on AttributeRouteLoader : https://github.com/debuss/attribute-routing
    $attributeRouteLoader = new AttributeRouteLoader('Application\\Handler', source_path('Application/Handler'));

    foreach ($attributeRouteLoader->getRouteDefinitions() as $routeDefinition) {
        $app->route(
            $routeDefinition->path,
            $routeDefinition->handler[0], // RequestHandlerInterface
            $routeDefinition->methods,
            $routeDefinition->name
        );
    }

    // You still can add route manually here
    $app->get('/api/health', HealthHandler::class, 'api.health');
};

<?php declare(strict_types=1);

use Borsch\Config\Config;
use Domain\Post\PostClientInterface;
use Http\Client\Curl\Client;
use Infrastructure\Post\PostClient;
use Piccolo\Container\ContainerBuilder;
use Piccolo\Environment;
use Psr\Http\Client\ClientInterface;

/*
 * ------------------------------------------
 * Container
 * ------------------------------------------
 *
 * The services of the framework (HTTP factories, logger, router, error handler, Problem Details, CORS, templates, ...)
 * are already registered by its service providers. Register the services of your application here, or in your own
 * service providers ($builder->addProvider(new MyServiceProvider())). A service registered again replaces the one of
 * the framework, e.g. another PSR-7 implementation:
 *
 *  $builder->addProvider(new HttpFactoryServiceProvider(new Psr17Factory(), new Psr17Factory(), ...));
 *
 * Each service has a lifetime:
 *  - singleton: a single instance for the whole application (in a FrankenPHP worker, for all the requests): it must
 *    not keep a state of a request;
 *  - scoped: a single instance per request (unit of work, current user, ...);
 *  - transient: a new instance on each resolution.
 *
 * The classes that are not registered (handlers, middlewares, ...) are autowired as transient services.
 *
 * For more information on the container: https://github.com/debuss/piccolo-container
 */
return static function (ContainerBuilder $builder, Config $config, Environment $environment): void
{
    // As an example here, the PostClientInterface is defined to be resolved to the PostClient class, the
    // implementations from Infrastructure being only wired to the Domain interfaces here.
    $builder->singleton(ClientInterface::class, Client::class);
    $builder->singleton(PostClientInterface::class, PostClient::class);
};

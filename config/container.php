<?php declare(strict_types=1);

use Awareness\ContainerAwareInterface;
use Application\ServiceProvider\{CacheServiceProvider,
    ClientServiceProvider,
    ConfigurationServiceProvider,
    DatabaseServiceProvider,
    ErrorHandlerServiceProvider,
    FastRouteRouterServiceProvider,
    HttpFactoryServiceProvider,
    LoggerServiceProvider,
    ProblemDetailsServiceProvider,
    RequestHandlerRunnerServiceProvider,
    TemplateRendererServiceProvider};
use League\Container\{Container, ReflectionContainer};
use Domain\Post\PostClientInterface;
use Infrastructure\Post\PostClient;
use Psr\Container\ContainerInterface;

/*
 * ------------------------------------------
 * Base League Container
 * ------------------------------------------
 *
 * This is the main container for the application. It is used to register and resolve services.
 * The container is configured to use the ReflectionContainer to automatically resolve dependencies.
 * The container is also configured to use the defaultToShared option, which means that services will be shared by
 * default.
 *
 * A ContainerInterface definition containing the container itself is also added in order to be retrieved from services
 * that need it.
 *
 * For more information on League Container: https://container.thephpleague.com/5.x/
 */

$container = new Container(defaultToShared: true);
$container->delegate(new ReflectionContainer());
$container->add(ContainerInterface::class, $container);

/*
 * ------------------------------------------
 * Service Providers
 * ------------------------------------------
 *
 * Here you can register your service providers. Service providers are classes that register services in the container.
 * The advantage of using service providers is that they allow you to organize your service definitions and dependencies
 * in a modular way, making your application easier to maintain and extend.
 * Moreover, they are only loaded if a service requires one of the definitions inside it, making your app lighter.
 *
 * A few providers below are commented as they might not be necessary, feel free to modify and enable them according to
 * your needs.
 *
 * For more information on service providers : https://container.thephpleague.com/5.x/service-providers/
 */

$container->addServiceProvider(new HttpFactoryServiceProvider());
$container->addServiceProvider(new ConfigurationServiceProvider());
$container->addServiceProvider(new LoggerServiceProvider());
$container->addServiceProvider(new RequestHandlerRunnerServiceProvider());
$container->addServiceProvider(new FastRouteRouterServiceProvider());
$container->addServiceProvider(new ErrorHandlerServiceProvider());
$container->addServiceProvider(new DatabaseServiceProvider());
$container->addServiceProvider(new TemplateRendererServiceProvider());
$container->addServiceProvider(new CacheServiceProvider());
$container->addServiceProvider(new ClientServiceProvider());
$container->addServiceProvider(new ProblemDetailsServiceProvider());

/*
 * ------------------------------------------
 * Definitions
 * ------------------------------------------
 *
 * Add your own definitions here that do not need to be in a service provider.
 *
 * As an example here, the PostClientInterface is defined to be resolved to the PostClient class.
 * You can also move it to a service provider if you prefer to keep your definitions organized in a separate class.
 *
 * For more information on definitions : https://container.thephpleague.com/5.x/definitions/
 */

$container->add(PostClientInterface::class, PostClient::class);

/*
 * ------------------------------------------
 * Events
 * ------------------------------------------
 *
 * The League Container event system provides a way to hook into the container’s lifecycle and modify services during
 * resolution.
 *
 * Here, services implementing ContainerAwareInterface have a method `setContainer()` and will be automatically injected
 * with the container instance after they are resolved (via the $class->setContainer($container)).
 *
 * This is particularly useful because you do not need to request the container in your constructor, keeping it
 * lightweight and clean.
 *
 * Other "Aware" interfaces are available for common PSR interfaces (ResponseFactoryAwareInterface,
 * StreamFactoryAwareInterface, ...).
 *
 * For more information on awareness : https://github.com/debuss/awareness
 * For more information on events : https://container.thephpleague.com/5.x/events
 */

$container->afterResolve(
    ContainerAwareInterface::class,
    static fn (ContainerAwareInterface $class) => $class->setContainer($container)
);

return $container;
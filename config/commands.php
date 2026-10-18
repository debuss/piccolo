<?php declare(strict_types=1);

use Application\Console\{CacheClearCommand, CacheWarmupCommand, RoutesListCommand};
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as Console;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

/**
 * Console commands configuration
 *
 * The commands are resolved from the container, with their dependencies, only when they are run (or listed): map the
 * name of your command to its class here.
 *
 *  'app:import-posts' => App\Console\ImportPostsCommand::class,
 *
 * @see https://symfony.com/doc/current/components/console.html
 */
return static function (Console $console, ContainerInterface $container): void
{
    $console->setCommandLoader(new ContainerCommandLoader($container, [
        'cache:clear' => CacheClearCommand::class,
        'cache:warmup' => CacheWarmupCommand::class,
        'routes:list' => RoutesListCommand::class,
    ]));
};

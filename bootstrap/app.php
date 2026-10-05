<?php declare(strict_types=1);

use Application\Environment;
use Borsch\Config\Config;
use League\Container\Container;

/*
 * ------------------------------------------
 * Configuration
 * ------------------------------------------
 *
 * The configuration is loaded first, the environment it defines drives both the PHP runtime settings and the services.
 */

/** @var Config $config */
$config = require config_path('configuration.php');
$environment = Environment::fromConfig($config);

/*
 * ------------------------------------------
 * PHP runtime settings
 * ------------------------------------------
 *
 * Errors are never displayed in production, they are handled by the ErrorHandler middleware and logged instead.
 */

ini_set('display_errors', $environment->isProduction() ? '0' : '1');
ini_set('display_startup_errors', $environment->isProduction() ? '0' : '1');

/*
 * ------------------------------------------
 * Container
 * ------------------------------------------
 *
 * The configuration and the environment are added to the container so that services can depend on them.
 */

/** @var Container $container */
$container = require config_path('container.php');

$container->add(Config::class, $config);
$container->add(Environment::class, $environment);

return $container;

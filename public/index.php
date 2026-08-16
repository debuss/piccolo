<?php declare(strict_types=1);

use Application\Application;
use Psr\Container\ContainerInterface;

// Delegate static file requests back to the PHP built-in webserver
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

require_once __DIR__ . '/../vendor/autoload.php';

(static function () {
    /** @var ContainerInterface $container */
    $container = require_once config_path('container.php');

    $application = $container->get(Application::class);

    (require_once config_path('pipeline.php'))($application, $container);
    (require_once config_path('routes.php'))($application, $container);

    $application->run();
})();

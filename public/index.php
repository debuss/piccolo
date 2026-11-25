<?php declare(strict_types=1);

use Piccolo\Kernel;

// Delegate static file requests back to the PHP built-in webserver
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

require_once __DIR__ . '/../vendor/autoload.php';

// Boots the application (configuration, container, pipeline and routes) then handles the request, or the requests in a
// loop when run by a FrankenPHP worker: see https://github.com/debuss/piccolo-framework
Kernel::boot(dirname(__DIR__))->http()->run();

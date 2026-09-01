<?php

define('__ROOT_DIR__', realpath(dirname(__DIR__)));

error_reporting(E_ALL);

$is_production = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development') === 'production';

ini_set('display_errors', $is_production ? '0' : '1');
ini_set('display_startup_errors', $is_production ? '0' : '1');

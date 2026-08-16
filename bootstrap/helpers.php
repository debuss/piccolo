<?php

/**
 * Returns the path to the root app directory, appended with extra path.
 */
function app_path(string ...$paths): string
{
    $path = array_reduce($paths, fn($acc, $cur) => $acc.DIRECTORY_SEPARATOR.ltrim($cur, ' /\\'), '');

    return __ROOT_DIR__.$path;
}

/**
 * Returns the path to the source directory, appended with extra path.
 */
function source_path(string ...$paths): string
{
    return app_path('src', ...$paths);
}

/**
 * Returns the path to the config directory, appended with extra path.
 */
function config_path(string ...$paths): string
{
    return app_path('config', ...$paths);
}

/**
 * Returns the path to the storage directory, appended with extra path.
 */
function storage_path(string ...$paths): string
{
    return app_path('storage', ...$paths);
}

/**
 * Returns the path to the cache directory, appended with extra path.
 */
function cache_path(string ...$paths): string
{
    return storage_path('cache', ...$paths);
}

/**
 * Returns the path to the logs directory, appended with extra path.
 */
function logs_path(string ...$paths): string
{
    return storage_path('logs', ...$paths);
}

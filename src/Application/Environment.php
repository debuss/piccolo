<?php declare(strict_types=1);

namespace Application;

use Borsch\Config\Config;

/**
 * Environment
 *
 * The environment the application runs in, resolved once from the `APP_ENV` configuration entry so that services do
 * not have to compare raw strings (and repeat the default value) on their own.
 * Unknown or missing values fall back to Development.
 */
enum Environment: string
{

    case Production = 'production';
    case Development = 'development';

    public static function fromConfig(Config $config): self
    {
        return self::tryFrom((string)$config->getOrDefault('APP_ENV')) ?? self::Development;
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}

<?php declare(strict_types=1);

use Borsch\Config\Aggregator;
use Borsch\Config\Provider\DotEnvProvider;

/*
 * Configuration sources, merged in order (the last one wins):
 *  1. .env file, optional (ignored if the file does not exist)
 *  2. Real environment variables (Docker ENV, nginx fastcgi_param, systemd, ...)
 */
return new Aggregator([
    new DotEnvProvider(app_path('.env')),
    getenv()
])->getMergedConfig();

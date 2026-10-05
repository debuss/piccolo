<?php declare(strict_types=1);

use Application\Environment;
use Borsch\Config\Config;

it('resolves the environment from APP_ENV', function (string $value, Environment $expected) {
    expect(Environment::fromConfig(new Config(['APP_ENV' => $value])))->toBe($expected);
})->with([
    'production' => ['production', Environment::Production],
    'development' => ['development', Environment::Development],
]);

it('falls back to development when APP_ENV is missing or unknown', function (array $config) {
    expect(Environment::fromConfig(new Config($config)))->toBe(Environment::Development);
})->with([
    'missing' => [[]],
    'unknown' => [['APP_ENV' => 'staging']],
]);

it('tells whether it is production', function () {
    expect(Environment::Production->isProduction())->toBeTrue()
        ->and(Environment::Development->isProduction())->toBeFalse();
});

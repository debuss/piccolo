<?php declare(strict_types=1);

use Application\Environment;
use Application\Http\RequestIdProcessor;
use Application\ServiceProvider\LoggerServiceProvider;
use Borsch\Config\Config;
use League\Container\Container;
use Monolog\Logger;

test('gives the logger the shared RequestIdProcessor, on which the middleware sets the identifier', function () {
    $container = new Container();
    $container->add(Config::class, new Config(['LOG_STREAM' => 'php://memory']));
    $container->add(Environment::class, Environment::Development);
    $container->addServiceProvider(new LoggerServiceProvider());

    $processor = $container->get(RequestIdProcessor::class);

    expect($container->get(RequestIdProcessor::class))->toBe($processor)
        ->and($container->get(Logger::class)->getProcessors())->toContain($processor);
});

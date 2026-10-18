<?php declare(strict_types=1);

use Application\Console\{CacheClearCommand, CacheWarmupCommand};
use Application\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fixtures\FakeContainer;

test('does nothing outside production, where the caches are disabled', function () {
    $directory = sys_get_temp_dir() . '/piccolo-cache-' . bin2hex(random_bytes(4));
    mkdir($directory);
    touch($directory . '/routes.cache.php');

    $tester = new CommandTester(new CacheWarmupCommand(
        Environment::Development,
        new CacheClearCommand($directory),
        new FakeContainer()
    ));

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and(preg_replace('/\s+/', ' ', $tester->getDisplay()))->toContain('only enabled in production')
        ->and(file_exists($directory . '/routes.cache.php'))->toBeTrue();

    unlink($directory . '/routes.cache.php');
    rmdir($directory);
});

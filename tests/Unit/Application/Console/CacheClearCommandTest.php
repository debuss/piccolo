<?php declare(strict_types=1);

use Application\Console\CacheClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A cache directory in the system temporary directory, with a .gitignore file, files and a nested directory.
 */
function makeCacheDirectory(): string
{
    $directory = sys_get_temp_dir() . '/piccolo-cache-' . bin2hex(random_bytes(4));

    mkdir($directory . '/templates', 0777, true);
    touch($directory . '/.gitignore');
    touch($directory . '/routes.cache.php');
    touch($directory . '/route-definitions.cache.php');
    touch($directory . '/templates/home.php');
    touch($directory . '/templates/.gitignore');

    return $directory;
}

test('removes the content of the cache directory, except its .gitignore file', function () {
    $directory = makeCacheDirectory();
    $tester = new CommandTester(new CacheClearCommand($directory));

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and(array_values(array_diff(scandir($directory), ['.', '..'])))->toBe(['.gitignore'])
        // SymfonyStyle wraps the long lines of its blocks
        ->and(preg_replace('/\s+/', ' ', $tester->getDisplay()))->toContain('4 file(s) removed');

    unlink($directory . '/.gitignore');
    rmdir($directory);
});

test('succeeds when the cache directory does not exist', function () {
    $directory = sys_get_temp_dir() . '/piccolo-missing-' . bin2hex(random_bytes(4));
    $tester = new CommandTester(new CacheClearCommand($directory));

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('nothing to clear');
});

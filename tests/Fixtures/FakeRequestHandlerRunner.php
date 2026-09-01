<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;

/**
 * Records whether run() was called, so tests can assert Application::run() delegates to it without actually
 * marshalling a request from PHP globals and emitting a response.
 */
final class FakeRequestHandlerRunner implements RequestHandlerRunnerInterface
{

    public bool $wasRun = false;

    public function run(): void
    {
        $this->wasRun = true;
    }
}

<?php declare(strict_types=1);

use Application\Http\RequestIdProcessor;
use Monolog\{Level, LogRecord};

function logRecord(array $extra = []): LogRecord
{
    return new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'message', extra: $extra);
}

test('adds the identifier of the request to the extra of the record', function () {
    $processor = new RequestIdProcessor();
    $processor->setRequestId('abc123');

    expect($processor(logRecord(['user' => 42]))->extra)->toBe(['user' => 42, 'request_id' => 'abc123']);
});

test('leaves the record unchanged outside a request', function () {
    $processor = new RequestIdProcessor();

    expect($processor(logRecord(['user' => 42]))->extra)->toBe(['user' => 42]);
});

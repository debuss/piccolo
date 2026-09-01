<?php declare(strict_types=1);

use Application\Http\ProblemDetails\ExceptionStatusMapper;
use Domain\Shared\Exception\NotFoundException;
use Infrastructure\Shared\Exception\ClientException;

test('maps NotFoundException to 404 Not Found', function () {
    $mapper = new ExceptionStatusMapper();

    expect($mapper->map(NotFoundException::create('Post', 42)))
        ->toBe(['status' => 404, 'title' => 'Not Found']);
});

test('maps ClientException to 502 Bad Gateway', function () {
    $mapper = new ExceptionStatusMapper();

    expect($mapper->map(ClientException::create('upstream unreachable')))
        ->toBe(['status' => 502, 'title' => 'Bad Gateway']);
});

test('returns null for an exception with no mapping', function () {
    $mapper = new ExceptionStatusMapper();

    expect($mapper->map(new RuntimeException('boom')))->toBeNull();
});

test('matches subclasses of a mapped exception, not just the exact class', function () {
    $mapper = new ExceptionStatusMapper();

    $subclass = new class ('sub not found') extends NotFoundException {};

    expect($mapper->map($subclass))->toBe(['status' => 404, 'title' => 'Not Found']);
});

test('a custom mapping table replaces the defaults instead of merging with them', function () {
    $mapper = new ExceptionStatusMapper([
        InvalidArgumentException::class => ['status' => 400, 'title' => 'Bad Request'],
    ]);

    expect($mapper->map(new InvalidArgumentException('bad input')))
        ->toBe(['status' => 400, 'title' => 'Bad Request']);

    // NotFoundException -> 404 is a default; it must not still apply once a custom map is supplied.
    expect($mapper->map(NotFoundException::create('Post', 1)))->toBeNull();
});

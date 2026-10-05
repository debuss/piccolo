<?php declare(strict_types=1);

use Application\Http\ServerRequestCreator;
use Laminas\Diactoros\{ServerRequestFactory, StreamFactory, UploadedFileFactory, UriFactory};
use Psr\Http\Message\UploadedFileInterface;

function makeServerRequestCreator(): ServerRequestCreator
{
    return new ServerRequestCreator(
        new ServerRequestFactory(),
        new UriFactory(),
        new UploadedFileFactory(),
        new StreamFactory()
    );
}

/**
 * @return array{tmp_name: string, size: int, error: int, name: string, type: string}
 */
function makeUploadedFileEntry(string $name, string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return ['tmp_name' => $path, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK, 'name' => $name, 'type' => 'text/plain'];
}

test('creates the request with method, protocol and server params', function () {
    $server = ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/posts/1', 'SERVER_PROTOCOL' => 'HTTP/2'];

    $request = makeServerRequestCreator()->fromArrays($server);

    expect($request->getMethod())->toBe('PUT')
        ->and($request->getProtocolVersion())->toBe('2')
        ->and($request->getServerParams())->toBe($server);
});

test('falls back to a GET request on / when the server params are empty', function () {
    $request = makeServerRequestCreator()->fromArrays([]);

    expect($request->getMethod())->toBe('GET')
        ->and($request->getProtocolVersion())->toBe('1.1')
        ->and((string)$request->getUri())->toBe('/');
});

test('builds the full URI from the Host header', function (array $server, string $expected) {
    expect((string)makeServerRequestCreator()->fromArrays($server)->getUri())->toBe($expected);
})->with([
    'http with port' => [
        ['HTTP_HOST' => 'localhost:8080', 'REQUEST_URI' => '/api/posts?page=2', 'QUERY_STRING' => 'page=2'],
        'http://localhost:8080/api/posts?page=2'
    ],
    'https' => [
        ['HTTPS' => 'on', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/'],
        'https://example.com/'
    ],
    'https off (IIS)' => [
        ['HTTPS' => 'off', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/'],
        'http://example.com/'
    ],
    'ipv6' => [
        ['HTTP_HOST' => '[::1]:8080', 'REQUEST_URI' => '/'],
        'http://[::1]:8080/'
    ],
    'query string without QUERY_STRING' => [
        ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/search?q=php'],
        'http://example.com/search?q=php'
    ],
    'Host header wins over SERVER_*' => [
        ['HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'internal', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'],
        'http://example.com/'
    ],
    'SERVER_* fallback, port given as a string' => [
        ['SERVER_NAME' => 'example.com', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'],
        'http://example.com:8080/'
    ],
]);

test('does not trust forwarded headers', function () {
    $request = makeServerRequestCreator()->fromArrays([
        'HTTP_HOST' => 'example.com',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'evil.com',
        'REQUEST_URI' => '/',
    ]);

    expect((string)$request->getUri())->toBe('http://example.com/');
});

test('extracts the headers from the server params', function () {
    $request = makeServerRequestCreator()->fromArrays([
        'HTTP_HOST' => 'example.com',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUEST_ID' => 'abc',
        'CONTENT_TYPE' => 'application/json',
        'CONTENT_LENGTH' => '18',
        'REQUEST_URI' => '/',
    ]);

    expect($request->getHeaderLine('Host'))->toBe('example.com')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('X-Request-Id'))->toBe('abc')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('Content-Length'))->toBe('18');
});

test('skips empty header values', function () {
    $request = makeServerRequestCreator()->fromArrays(['CONTENT_TYPE' => '', 'CONTENT_LENGTH' => '']);

    expect($request->hasHeader('Content-Type'))->toBeFalse()
        ->and($request->hasHeader('Content-Length'))->toBeFalse();
});

test('restores the Authorization header stripped by Apache', function (array $server, string $expected) {
    expect(makeServerRequestCreator()->fromArrays($server)->getHeaderLine('Authorization'))->toBe($expected);
})->with([
    'redirect' => [['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer token'], 'Bearer token'],
    'basic auth' => [['PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'secret'], 'Basic '.base64_encode('user:secret')],
    'header wins' => [['HTTP_AUTHORIZATION' => 'Bearer header', 'PHP_AUTH_USER' => 'user'], 'Bearer header'],
]);

test('sets query params, parsed body, cookies and body', function () {
    $body = new StreamFactory()->createStream('{"title":"Bonjour"}');

    $request = makeServerRequestCreator()->fromArrays(
        ['REQUEST_METHOD' => 'POST'],
        ['page' => '2'],
        ['title' => 'Bonjour'],
        ['session' => 'abc'],
        [],
        $body
    );

    expect($request->getQueryParams())->toBe(['page' => '2'])
        ->and($request->getParsedBody())->toBe(['title' => 'Bonjour'])
        ->and($request->getCookieParams())->toBe(['session' => 'abc'])
        ->and((string)$request->getBody())->toBe('{"title":"Bonjour"}');
});

test('normalizes a single uploaded file', function () {
    $request = makeServerRequestCreator()->fromArrays([], files: [
        'avatar' => makeUploadedFileEntry('avatar.txt', 'hello'),
    ]);

    $file = $request->getUploadedFiles()['avatar'];

    expect($file)->toBeInstanceOf(UploadedFileInterface::class)
        ->and($file->getClientFilename())->toBe('avatar.txt')
        ->and($file->getClientMediaType())->toBe('text/plain')
        ->and($file->getSize())->toBe(5)
        ->and($file->getError())->toBe(UPLOAD_ERR_OK)
        ->and((string)$file->getStream())->toBe('hello');
});

test('normalizes multiple and nested uploaded files', function () {
    $first = makeUploadedFileEntry('first.txt', 'one');
    $second = makeUploadedFileEntry('second.txt', 'two');
    $cv = makeUploadedFileEntry('cv.txt', 'cv');

    // As given by PHP for `<input name="files[]">` and `<input name="doc[cv]">`
    $request = makeServerRequestCreator()->fromArrays([], files: [
        'files' => [
            'tmp_name' => [$first['tmp_name'], $second['tmp_name']],
            'size' => [3, 3],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'name' => ['first.txt', 'second.txt'],
            'type' => ['text/plain', 'text/plain'],
        ],
        'doc' => [
            'tmp_name' => ['cv' => $cv['tmp_name']],
            'size' => ['cv' => 2],
            'error' => ['cv' => UPLOAD_ERR_OK],
            'name' => ['cv' => 'cv.txt'],
            'type' => ['cv' => 'text/plain'],
        ],
    ]);

    $files = $request->getUploadedFiles();

    expect($files['files'])->toHaveCount(2)
        ->and($files['files'][0]->getClientFilename())->toBe('first.txt')
        ->and((string)$files['files'][1]->getStream())->toBe('two')
        ->and($files['doc']['cv']->getClientFilename())->toBe('cv.txt')
        ->and((string)$files['doc']['cv']->getStream())->toBe('cv');
});

test('keeps failed uploads with their error and an empty stream', function () {
    $request = makeServerRequestCreator()->fromArrays([], files: [
        'avatar' => ['tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'type' => ''],
    ]);

    $file = $request->getUploadedFiles()['avatar'];

    expect($file->getError())->toBe(UPLOAD_ERR_NO_FILE);
});

test('keeps already normalized uploaded files', function () {
    $file = new UploadedFileFactory()->createUploadedFile(new StreamFactory()->createStream('hello'));

    $request = makeServerRequestCreator()->fromArrays([], files: ['avatar' => $file]);

    expect($request->getUploadedFiles()['avatar'])->toBe($file);
});

test('rejects an invalid uploaded files specification', function () {
    makeServerRequestCreator()->fromArrays([], files: ['avatar' => 'not a file']);
})->throws(InvalidArgumentException::class);

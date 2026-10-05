<?php declare(strict_types=1);

namespace Application\Http;

use InvalidArgumentException;
use Psr\Http\Message\{ServerRequestFactoryInterface,
    ServerRequestInterface,
    StreamFactoryInterface,
    StreamInterface,
    UploadedFileFactoryInterface,
    UploadedFileInterface,
    UriFactoryInterface,
    UriInterface};

/**
 * Server Request Creator
 *
 * PSR-17 `createServerRequest()` only takes a method, a URI and the server params: headers, cookies, query, parsed
 * body, uploaded files and body are left empty.
 * This class fills the request from the PHP superglobals (or given arrays) using only PSR-17 factories, so it works
 * the same whatever PSR-7 implementation is registered in the container.
 *
 * Forwarded headers (X-Forwarded-Proto, X-Forwarded-Host, ...) are NOT trusted on purpose, as they can be spoofed by
 * clients when the application is not behind a trusted proxy. Handle them in a dedicated middleware if needed.
 */
readonly class ServerRequestCreator
{

    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private UriFactoryInterface $uriFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private StreamFactoryInterface $streamFactory
    ) {}

    public function fromGlobals(): ServerRequestInterface
    {
        // $_POST is only populated by PHP for form submissions, any other body (JSON, ...) is left to be parsed later
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        $isForm = in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true);

        return $this->fromArrays(
            $_SERVER,
            $_GET,
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $isForm ? $_POST : null,
            $_COOKIE,
            $_FILES,
            $this->streamFactory->createStreamFromFile('php://input')
        );
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     */
    public function fromArrays(
        array $server,
        array $query = [],
        ?array $parsedBody = null,
        array $cookies = [],
        array $files = [],
        ?StreamInterface $body = null
    ): ServerRequestInterface {
        $request = $this->serverRequestFactory
            ->createServerRequest($server['REQUEST_METHOD'] ?? 'GET', $this->createUri($server), $server)
            ->withProtocolVersion(str_replace('HTTP/', '', $server['SERVER_PROTOCOL'] ?? '1.1'))
            ->withQueryParams($query)
            ->withParsedBody($parsedBody)
            ->withCookieParams($cookies)
            ->withUploadedFiles($this->normalizeFiles($files))
            ->withBody($body ?? $this->streamFactory->createStream());

        foreach ($this->getHeaders($server) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private function getHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            // Empty values are skipped, some servers (nginx, ...) always send CONTENT_TYPE and CONTENT_LENGTH
            if (!is_string($key) || !is_scalar($value) || (string)$value === '') {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string)$value;
            } elseif (str_starts_with($key, 'CONTENT_')) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string)$value;
            }
        }

        // Apache can strip the Authorization header, it is then only available in these entries
        if (!isset($headers['authorization'])) {
            if (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = (string)$server['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($server['PHP_AUTH_USER'])) {
                $headers['authorization'] = 'Basic '.base64_encode($server['PHP_AUTH_USER'].':'.($server['PHP_AUTH_PW'] ?? ''));
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function createUri(array $server): UriInterface
    {
        $uri = $this->uriFactory->createUri();

        // The Host header contains the port when it is not the default one, SERVER_* is only used as a fallback
        if (isset($server['HTTP_HOST']) && preg_match('/^(\[[^]]+]|[^:]+)(?::(\d+))?$/', $server['HTTP_HOST'], $matches)) {
            $uri = $uri->withHost($matches[1]);
            if (isset($matches[2])) {
                $uri = $uri->withPort((int)$matches[2]);
            }
        } elseif (isset($server['SERVER_NAME'])) {
            $uri = $uri->withHost($server['SERVER_NAME']);
            if (isset($server['SERVER_PORT'])) {
                $uri = $uri->withPort((int)$server['SERVER_PORT']);
            }
        }

        // Without host (CLI, ...), a scheme would give an invalid URI like "http:/"
        if ($uri->getHost() !== '') {
            $https = strtolower((string)($server['HTTPS'] ?? 'off'));
            $uri = $uri->withScheme($https !== '' && $https !== 'off' ? 'https' : 'http');
        }

        $parts = explode('?', $server['REQUEST_URI'] ?? '/', 2);

        return $uri
            ->withPath($parts[0])
            ->withQuery($server['QUERY_STRING'] ?? $parts[1] ?? '');
    }

    /**
     * Normalizes the $_FILES structure into a tree of UploadedFileInterface, as required by PSR-7.
     *
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof UploadedFileInterface => $value,
                is_array($value) && isset($value['tmp_name']) => $this->createUploadedFiles($value),
                is_array($value) => $this->normalizeFiles($value),
                default => throw new InvalidArgumentException('Invalid value in uploaded files specification.')
            };
        }

        return $normalized;
    }

    /**
     * Fields like `files[]` or `doc[cv]` are given by PHP as `['name' => ['cv' => ...], 'tmp_name' => ['cv' => ...]]`,
     * they are turned back into one file per key, recursively.
     *
     * @param array<string, mixed> $file
     * @return UploadedFileInterface|array<string, mixed>
     */
    private function createUploadedFiles(array $file): UploadedFileInterface|array
    {
        if (is_array($file['tmp_name'])) {
            $files = [];
            foreach (array_keys($file['tmp_name']) as $key) {
                $files[$key] = $this->createUploadedFiles([
                    'tmp_name' => $file['tmp_name'][$key],
                    'size' => $file['size'][$key] ?? null,
                    'error' => $file['error'][$key] ?? UPLOAD_ERR_OK,
                    'name' => $file['name'][$key] ?? null,
                    'type' => $file['type'][$key] ?? null
                ]);
            }

            return $files;
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_OK);

        return $this->uploadedFileFactory->createUploadedFile(
            // A failed upload has no temporary file to read from
            $error === UPLOAD_ERR_OK
                ? $this->streamFactory->createStreamFromFile($file['tmp_name'])
                : $this->streamFactory->createStream(),
            isset($file['size']) ? (int)$file['size'] : null,
            $error,
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }
}

<?php declare(strict_types=1);

namespace Application\Handler;

use JsonException;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Awareness\{ResponseFactoryAwareInterface,
    ResponseFactoryAwareTrait,
    StreamFactoryAwareInterface,
    StreamFactoryAwareTrait};

/**
 * Base class for the request handlers, building responses with the PSR-17 factories (injected after resolution by
 * the container, see HttpFactoryServiceProvider), so that handlers do not depend on a PSR-7 implementation.
 */
abstract class Handler implements RequestHandlerInterface, ResponseFactoryAwareInterface, StreamFactoryAwareInterface
{

    use ResponseFactoryAwareTrait, StreamFactoryAwareTrait;

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @throws JsonException When the data cannot be encoded
     */
    protected function json(
        array|JsonSerializable $data,
        int $status = 200,
        int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        int $depth = 512
    ): ResponseInterface {
        $json = json_encode($data, $flags | JSON_THROW_ON_ERROR, $depth);

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json');
    }
}

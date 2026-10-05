<?php

namespace Application\Handler;

use JsonException;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Awareness\{ResponseFactoryAwareInterface,
    ResponseFactoryAwareTrait,
    StreamFactoryAwareInterface,
    StreamFactoryAwareTrait};
use RuntimeException;

class Handler implements ResponseFactoryAwareInterface, StreamFactoryAwareInterface
{

    use ResponseFactoryAwareTrait, StreamFactoryAwareTrait;

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', 'text/html');
    }

    protected function json(
        array|JsonSerializable $data,
        int $status = 200,
        int $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        int $depth = 512
    ): ResponseInterface {
        try {
            $json = json_encode($data, $flags | JSON_THROW_ON_ERROR, $depth);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode data to JSON: ' . $e->getMessage(), previous: $e);
        }

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json');
    }
}

<?php declare(strict_types=1);

namespace Application\Http;

use Application\Environment;
use Debuss\ServerRequestFactory\BadRequestException;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface};
use Psr\Log\{LoggerInterface, LogLevel};
use Throwable;

/**
 * Server Request Error Response Generator
 *
 * Generates the response when the server request cannot be created from the globals (invalid header, malformed
 * uploaded files, ...). The request never reaches the middleware pipeline in this case, so neither the ErrorHandler
 * nor its logger are involved: the error is logged here.
 *
 * A malformed client request (BadRequestException) gives a 400 and is logged as a warning, as there is nothing to fix
 * on our side. Any other error (invalid configuration, unreadable uploaded file, ...) comes from the server: it gives a
 * 500 and is logged as an error. The exception is only displayed outside of production.
 *
 * @see https://docs.laminas.dev/laminas-httphandlerrunner/runner/
 */
readonly class ServerRequestErrorResponseGenerator
{

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
        private Environment $environment
    ) {}

    public function __invoke(Throwable $e): ResponseInterface
    {
        $isBadRequest = $e instanceof BadRequestException;

        $this->logger->log($isBadRequest ? LogLevel::WARNING : LogLevel::ERROR, $e->getMessage(), ['exception' => $e]);

        $response = $this->responseFactory->createResponse($isBadRequest ? 400 : 500);
        $response->getBody()->write(
            $this->environment->isProduction() ? $response->getReasonPhrase() : (string)$e
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

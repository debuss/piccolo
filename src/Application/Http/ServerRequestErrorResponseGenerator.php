<?php declare(strict_types=1);

namespace Application\Http;

use Application\Environment;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface};
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Server Request Error Response Generator
 *
 * Generates the response when the server request cannot be created from the globals (invalid header, malformed
 * uploaded files, ...). The request never reaches the middleware pipeline in this case, so neither the ErrorHandler
 * nor its logger are involved: the error is logged here.
 *
 * It is almost always caused by a malformed client request, hence the 400 status. The exception is only displayed
 * outside of production.
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
        $this->logger->warning($e->getMessage(), ['exception' => $e]);

        $response = $this->responseFactory->createResponse(400);
        $response->getBody()->write(
            $this->environment->isProduction() ? $response->getReasonPhrase() : (string)$e
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

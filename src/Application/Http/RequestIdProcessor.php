<?php declare(strict_types=1);

namespace Application\Http;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * RequestIdProcessor
 *
 * Adds the identifier of the request being handled, set by the RequestIdMiddleware, to the `extra` of every log record,
 * so that all the logs of a request can be found. The records logged outside a request (console, ...) are unchanged.
 *
 * It must be a shared service: the middleware sets the identifier on the instance given to the logger.
 */
class RequestIdProcessor implements ProcessorInterface
{

    private ?string $requestId = null;

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->requestId === null) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'request_id' => $this->requestId]);
    }
}

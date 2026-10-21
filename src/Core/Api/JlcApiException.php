<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

use RuntimeException;

/**
 * A failed call: not reachable, HTTP error, or a business error code in the body. Carries the J-Trace-ID JLC support asks for.
 */
final class JlcApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $traceId = '')
    {
        parent::__construct($traceId === '' ? $message : $message . ' [J-Trace-ID ' . $traceId . ']');
    }
}

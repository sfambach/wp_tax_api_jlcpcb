<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Api\JlcApiException;
use Taxmod\Core\Http\HttpTransport;
use Taxmod\WordPress\Http\WpHttpTransport;

/** This module's way to the web: the modeller's one HTTP block (D-1415) with this module's failure and trace header. */
final class ApiTransport
{
    public static function make(): HttpTransport
    {
        return new WpHttpTransport(static fn (string $why): \Throwable => new JlcApiException('JLC not reachable: ' . $why), 'j-trace-id');
    }
}

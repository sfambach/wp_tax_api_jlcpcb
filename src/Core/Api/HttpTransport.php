<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Sends a request. The boundary implements it over WordPress; tests script it.
 */
interface HttpTransport
{
    /** @throws JlcApiException when nothing came back */
    public function send(HttpRequest $request): HttpResponse;
}

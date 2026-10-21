<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Api\HttpRequest;
use TaxApiJlcpcb\Core\Api\HttpResponse;
use TaxApiJlcpcb\Core\Api\HttpTransport;
use TaxApiJlcpcb\Core\Api\JlcApiException;

/**
 * The core's HTTP port over `wp_remote_request`.
 */
final class WpHttpTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $answer = wp_remote_request($request->url, [
            'method'  => $request->method,
            'headers' => $request->headers,
            'body'    => $request->body === '' ? null : $request->body,
            'timeout' => 20,
        ]);

        if (is_wp_error($answer)) {
            throw new JlcApiException('JLC not reachable: ' . $answer->get_error_message());
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($answer),
            (string) wp_remote_retrieve_body($answer),
            (string) wp_remote_retrieve_header($answer, 'j-trace-id')
        );
    }
}

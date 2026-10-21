<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Signed POST to the JLC Open Platform. HTTP 200 only means «received»; success is `code` 200 in the body.
 *
 * @see https://api.jlcpcb.com/docs/start
 */
final class JlcClient
{
    public const BASE_URL = 'https://open.jlcpcb.com';

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /** @param (\Closure(): int)|null $clock seconds since the epoch; tests fix it */
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly Credentials $credentials,
        private readonly string $baseUrl = self::BASE_URL,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed the `data` member, whose shape depends on the endpoint
     */
    public function post(string $path, array $payload): mixed
    {
        // The signed body and the sent body must be byte-identical.
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $header = (new RequestSigner($this->credentials))->header('POST', $path, ($this->clock)(), RequestSigner::nonce(), $body);

        $response = $this->transport->send(new HttpRequest('POST', rtrim($this->baseUrl, '/') . $path, [
            'Authorization' => $header,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $body));

        $json = $response->json();

        if ($response->status !== 200) {
            throw new JlcApiException(sprintf('HTTP %d: %s', $response->status, self::message($json, $response->body)), $response->traceId);
        }

        if ((int) ($json['code'] ?? 0) !== 200) {
            throw new JlcApiException(sprintf('JLC error %s: %s', (string) ($json['code'] ?? '?'), self::message($json, $response->body)), $response->traceId);
        }

        return $json['data'] ?? null;
    }

    /** @param array<string, mixed> $json */
    private static function message(array $json, string $body): string
    {
        $message = $json['message'] ?? $json['msg'] ?? null;

        return is_string($message) && $message !== '' ? $message : mb_substr($body, 0, 200);
    }
}

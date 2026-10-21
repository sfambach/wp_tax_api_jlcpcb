<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Builds the `Authorization: JOP …` header of the JLC Open Platform: HMAC-SHA256 over method, path, timestamp,
 * nonce and body, each followed by a newline, Base64-encoded.
 *
 * @see https://api.jlcpcb.com/docs/api-request-signature
 */
final class RequestSigner
{
    private const NONCE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(private readonly Credentials $credentials)
    {
    }

    public function signature(string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        $text = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        return base64_encode(hash_hmac('sha256', $text, $this->credentials->secretKey, true));
    }

    public function header(string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        return sprintf(
            'JOP appid="%s",accesskey="%s",nonce="%s",timestamp="%d",signature="%s"',
            $this->credentials->appId,
            $this->credentials->accessKey,
            $nonce,
            $timestamp,
            $this->signature($method, $path, $timestamp, $nonce, $body)
        );
    }

    /** 32 characters from A–Z, a–z, 0–9, as the platform demands. */
    public static function nonce(): string
    {
        $nonce = '';

        for ($i = 0; $i < 32; $i++) {
            $nonce .= self::NONCE_ALPHABET[random_int(0, strlen(self::NONCE_ALPHABET) - 1)];
        }

        return $nonce;
    }
}

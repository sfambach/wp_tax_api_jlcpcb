<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Status, body and trace id of an answer; transport failures are exceptions, not responses.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $traceId = '',
    ) {
    }

    /** @return array<string, mixed> `mixed` because the shape of `data` differs by endpoint */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}

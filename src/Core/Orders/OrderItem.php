<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * One PCB order of a batch: its Gerber name, the number of boards, the fee (USD) and the status. JLC's own field names
 * say «stencil» for the board (`stencilCounts`), not for an SMT stencil.
 */
final class OrderItem
{
    public function __construct(
        public readonly string $gerberName,
        public readonly ?int $count,
        public readonly ?float $fee,
        public readonly ?int $status,
        public readonly string $orderCode,
    ) {
    }

    /** @param array<string, mixed> $row one element of `pcbOrderInfoDataList` */
    public static function fromRow(array $row): self
    {
        $fees = is_array($row['pcbOrderFeeInfo'] ?? null) ? $row['pcbOrderFeeInfo'] : [];

        return new self(
            is_string($row['gerberName'] ?? null) ? $row['gerberName'] : '',
            is_numeric($row['stencilCounts'] ?? null) ? (int) $row['stencilCounts'] : null,
            is_numeric($fees['totalFee'] ?? null) ? (float) $fees['totalFee'] : null,
            is_numeric($row['orderStatus'] ?? null) ? (int) $row['orderStatus'] : null,
            is_string($row['orderCode'] ?? null) ? $row['orderCode'] : '',
        );
    }

    /** Status 0 is «cancelled» in JLC's first version of the call; V2 names no codes (assumed unchanged). */
    public function isCancelled(): bool
    {
        return $this->status === 0;
    }
}

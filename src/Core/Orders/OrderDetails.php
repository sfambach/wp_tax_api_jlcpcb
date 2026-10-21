<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * What JLC says about one batch: shipping method and fee, customs duty, tracking number and the PCB orders (USD).
 * The address of the answer — name, street, phone — is deliberately not read: it is never stored.
 */
final class OrderDetails
{
    /** @param list<OrderItem> $items */
    public function __construct(
        public readonly string $batchNumber,
        public readonly ?string $shippingMethod,
        public readonly ?float $shippingCost,
        public readonly ?float $customsDuty,
        public readonly ?string $trackingNumber,
        public readonly array $items,
        public readonly int $stencilOrders = 0,
        /** @var array<string, string> money-like fields of the raw answer, for the probe */
        public readonly array $moneyFields = [],
    ) {
    }

    /** @param array<string, mixed> $data the `data` member of the answer */
    public static function fromData(string $batchNumber, array $data): self
    {
        $number = static fn (string $key): ?float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : null;
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) && $data[$key] !== '' ? $data[$key] : null;
        $items = [];

        foreach (is_array($data['pcbOrderInfoDataList'] ?? null) ? $data['pcbOrderInfoDataList'] : [] as $row) {
            if (is_array($row)) {
                $items[] = OrderItem::fromRow($row);
            }
        }

        return new self(
            $batchNumber,
            $text('shippingMethod'),
            $number('totalCarriageMoney'),
            $number('tariffMoney'),
            $text('expressNo'),
            $items,
            is_array($data['steelOrderAggInfoList'] ?? null) ? count($data['steelOrderAggInfoList']) : 0,
            MoneyFields::from($data),
        );
    }

    /** Fees of the orders not cancelled, plus shipping and customs; null when no fee is known. */
    public function total(): ?float
    {
        $fees = array_filter(array_map(static fn (OrderItem $i): ?float => $i->isCancelled() ? null : $i->fee, $this->items), static fn (?float $f): bool => $f !== null);

        return $fees === [] ? null : array_sum($fees) + ($this->shippingCost ?? 0.0) + ($this->customsDuty ?? 0.0);
    }

    /** The batch number starts with its creation date: `W` + yyyyMMdd… (seen in every example). */
    public function orderDate(): ?string
    {
        return preg_match('/^W(\d{4})(\d{2})(\d{2})/', $this->batchNumber, $m) === 1 ? sprintf('%s-%s-%s', $m[1], $m[2], $m[3]) : null;
    }
}

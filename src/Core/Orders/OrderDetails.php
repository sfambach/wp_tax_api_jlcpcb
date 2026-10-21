<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

use DateTimeImmutable;
use DateTimeZone;

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
        // Stencil orders are counted but not read yet, so a sum of the PCB fees alone would be too low.
        if ($this->stencilOrders > 0) {
            return null;
        }

        $fees = array_filter(array_map(static fn (OrderItem $i): ?float => $i->isCancelled() ? null : $i->fee, $this->items), static fn (?float $f): bool => $f !== null);

        return $fees === [] ? null : array_sum($fees) + ($this->shippingCost ?? 0.0) + ($this->customsDuty ?? 0.0);
    }

    /**
     * The batch number starts with its creation time in China Standard Time: `W` + yyyyMMddHHmm… (seen in every
     * example). JLC's website shows the same moment in the viewer's zone — W2026092101021667 is «2026-09-20 19:02» in
     * Germany — so the date is taken in the zone the caller names.
     */
    public function orderDate(DateTimeZone $zone): ?string
    {
        if (preg_match('/^W(\d{12})/', $this->batchNumber, $m) !== 1) {
            return null;
        }

        $moment = DateTimeImmutable::createFromFormat('YmdHi', $m[1], new DateTimeZone('Asia/Shanghai'));

        return $moment === false ? null : $moment->setTimezone($zone)->format('Y-m-d');
    }
}

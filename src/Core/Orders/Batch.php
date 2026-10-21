<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * One batch of the list: its number (`W…`, the order number of the mails) and how many orders of which type it holds.
 */
final class Batch
{
    /** @param array<string, int> $ordersByType e.g. ['order_pcb' => 2] */
    public function __construct(
        public readonly string $number,
        public readonly array $ordersByType,
    ) {
    }

    /** @param array<string, mixed> $row one element of `list` */
    public static function fromRow(array $row): self
    {
        $types = [];

        foreach (is_array($row['orderTypeInfos'] ?? null) ? $row['orderTypeInfos'] : [] as $info) {
            if (is_array($info) && is_string($info['batchOrderType'] ?? null)) {
                $types[$info['batchOrderType']] = (int) ($info['orderNum'] ?? 0);
            }
        }

        return new self((string) $row['batchNum'], $types);
    }
}

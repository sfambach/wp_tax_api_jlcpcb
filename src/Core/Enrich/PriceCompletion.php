<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Enrich;

use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalLine;
use TaxApiJlcpcb\Core\Orders\OrderDetails;
use TaxApiJlcpcb\Core\Orders\OrderItem;

/**
 * Adds to a JLCPCB proposal what its mails never name: unit prices of the boards, shipping, total, currency (USD),
 * order date and tracking number. It only fills what is empty and adds no line (D-953). Customs duty counts into the
 * total; the proposal has no field of its own for it.
 *
 * A line finds its item by the board name: the mail parser of tax_mail titles a board «Leiterplatte <name>» from the
 * Gerber file name, so both sides are compared after the same normalisation.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class PriceCompletion
{
    public const CURRENCY = 'USD';

    public function complete(OrderProposal $proposal, OrderDetails $details): ?OrderProposal
    {
        if ($proposal->currency !== self::CURRENCY && self::hasMoney($proposal)) {
            // Prices in another currency came from somewhere else; mixing USD into them would be wrong.
            return null;
        }

        $items = array_values(array_filter($details->items, static fn (OrderItem $item): bool => ! $item->isCancelled()));
        $lines = [];
        $changed = false;

        foreach ($proposal->lines as $line) {
            $item = $line->unitPrice === null ? self::match($line, $items) : null;
            $unitPrice = $item === null ? null : self::unitPrice($item, $line);

            if ($unitPrice === null) {
                $lines[] = $line;
                continue;
            }

            $lines[] = new ProposalLine($line->title, $line->quantity ?? (float) $item->count, $unitPrice, $line->articleNumber);
            $changed = true;
        }

        $shipping = $proposal->shippingCost ?? self::money($details->shippingCost);
        $total = $proposal->total ?? self::money($details->total());
        $date = $proposal->orderDate ?? $details->orderDate();
        $tracking = $proposal->trackingNumbers === [] && $details->trackingNumber !== null ? [$details->trackingNumber] : $proposal->trackingNumbers;
        $changed = $changed || $shipping !== $proposal->shippingCost || $total !== $proposal->total || $date !== $proposal->orderDate
            || $tracking !== $proposal->trackingNumbers;

        if (! $changed) {
            return null;
        }

        $row = $proposal->toArray();
        $row['lines'] = array_map(static fn (ProposalLine $l): array => $l->toArray(), $lines);
        $row['shipping_cost'] = $shipping;
        $row['total'] = $total;
        $row['order_date'] = $date;
        $row['tracking'] = $tracking;
        // Its prices were empty, so its currency was only the default; JLC bills in USD.
        $row['currency'] = self::CURRENCY;

        return OrderProposal::fromArray($row);
    }

    private static function hasMoney(OrderProposal $proposal): bool
    {
        foreach ($proposal->lines as $line) {
            if ($line->unitPrice !== null) {
                return true;
            }
        }

        return $proposal->shippingCost !== null || $proposal->total !== null;
    }

    /** @param list<OrderItem> $items */
    private static function match(ProposalLine $line, array $items): ?OrderItem
    {
        $key = self::normalise(preg_replace('/^Leiterplatte\s+/u', '', $line->title) ?? $line->title);

        foreach ($items as $item) {
            $other = self::normalise($item->gerberName);

            if ($key !== '' && $other !== '' && ($other === $key || str_starts_with($other, $key) || str_starts_with($key, $other))) {
                return $item;
            }
        }

        return null;
    }

    private static function unitPrice(OrderItem $item, ProposalLine $line): ?string
    {
        $count = $item->count ?? ($line->quantity !== null ? (int) $line->quantity : null);

        return $item->fee === null || $count === null || $count <= 0 ? null : self::money($item->fee / $count, 4);
    }

    private static function money(?float $amount, int $decimals = 2): ?string
    {
        if ($amount === null) {
            return null;
        }

        $text = rtrim(rtrim(number_format($amount, $decimals, '.', ''), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }

    /** Same rule as tax_mail's JLCPCB parser: no file number (_Y87), no «gerber», no bracket, «-» and «_» alike. */
    private static function normalise(string $name): string
    {
        $n = preg_replace('/(\.{3}|…)$/u', '', trim($name)) ?? $name;
        $n = explode('(', $n)[0];
        $n = preg_replace('/\.(zip|rar|7z)$/i', '', $n) ?? $n;
        $n = preg_replace('/_Y\d+/', '', $n) ?? $n;
        $n = preg_replace('/[_ ]?gerber/i', '', $n) ?? $n;

        return strtolower(trim(preg_replace('/[-_ ]+/', '_', $n) ?? $n, '_'));
    }
}

<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Feed;

use DateTimeImmutable;
use DateTimeZone;
use Taxmod\Core\Import\FetchBatch;
use Taxmod\Core\Import\IncomingProposal;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;
use Taxmod\Core\Import\ProposalLine;
use TaxApiJlcpcb\Core\Orders\BatchList;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;
use TaxApiJlcpcb\Core\Orders\OrderDetails;
use TaxApiJlcpcb\Core\Orders\OrderItem;

/**
 * One run of the JLCPCB source for the modeller's inbox (D-965): the batches since the last run, each as a proposal
 * with the batch number as order number and external id. The first run reaches back a year, which is the first
 * comparison with the model. Amounts stay in USD (INF-085). The cursor is the start of the previous run; a day of
 * overlap catches late batches, and the inbox drops what it already holds.
 */
final class OrderFeed
{
    public const PLATFORM  = 'JLCPCB';
    public const SOURCE    = 'jlcpcb';
    public const DAYS_BACK = 365;
    private const OVERLAP  = '-1 day';

    public function __construct(
        private readonly BatchList $batches,
        private readonly OrderDetailQuery $details,
        private readonly DateTimeZone $zone,
    ) {
    }

    public function run(?string $cursor, DateTimeImmutable $now): FetchBatch
    {
        $oldest = $now->modify('-' . self::DAYS_BACK . ' days');
        $from = $cursor === null ? $oldest : max($oldest, (new DateTimeImmutable($cursor))->modify(self::OVERLAP));
        $numbers = [];

        foreach ([BatchList::PCB, BatchList::STENCIL] as $type) {
            foreach ($this->batches->between($type, $from, $now) as $batch) {
                $numbers[$batch->number] = true;
            }
        }

        $proposals = [];

        foreach (array_keys($numbers) as $number) {
            $proposal = self::proposal($this->details->batch((string) $number), $this->zone);

            if ($proposal !== null) {
                $proposals[] = $proposal;
            }
        }

        return new FetchBatch($proposals, $now->format(DATE_ATOM));
    }

    /** Null for a batch whose every order was cancelled. */
    public static function proposal(OrderDetails $details, DateTimeZone $zone): ?IncomingProposal
    {
        $items = array_values(array_filter($details->items, static fn (OrderItem $item): bool => ! $item->isCancelled()));

        if ($items === [] && $details->stencilOrders === 0) {
            return null;
        }

        $lines = array_map(static fn (OrderItem $item): ProposalLine => new ProposalLine(
            BoardTitle::of($item->gerberName),
            $item->count === null ? null : (float) $item->count,
            $item->fee === null || $item->count === null || $item->count <= 0 ? null : self::money($item->fee / $item->count, 4),
        ), $items);

        $proposal = new OrderProposal(
            self::PLATFORM,
            $details->batchNumber,
            ProposalKind::Ordered,
            $details->orderDate($zone),
            'USD',
            $lines,
            self::money($details->shippingCost),
            self::money($details->total()),
            counterparty: 'JLCPCB',
            trackingNumbers: $details->trackingNumber === null ? [] : [$details->trackingNumber],
            source: self::SOURCE,
            externalId: $details->batchNumber,
        );

        return new IncomingProposal($proposal, [
            'boards'   => count($items),
            'stencils' => $details->stencilOrders,
            'shipping' => $details->shippingMethod,
            'customs'  => self::money($details->customsDuty),
        ]);
    }

    private static function money(?float $amount, int $decimals = 2): ?string
    {
        if ($amount === null) {
            return null;
        }

        $text = rtrim(rtrim(number_format($amount, $decimals, '.', ''), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }
}

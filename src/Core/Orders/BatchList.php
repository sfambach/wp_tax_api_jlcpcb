<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

use DateTimeImmutable;
use DateTimeZone;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;

/**
 * The batches (orders `W…`) created in a period, per order type, read page by page. Only numbers and order types —
 * prices and contents need the detail call per batch.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class BatchList
{
    public const PATH       = '/overseas/openapi/pcb/pageBatchInfoByOrderType';
    public const PCB        = 'order_pcb';
    public const STENCIL    = 'order_steel';
    private const PAGE_SIZE = 50;

    /** A runaway guard: 20 pages of 50 are far more than one person orders in a period. */
    private const MAX_PAGES = 20;

    public function __construct(private readonly JlcClient $client)
    {
    }

    /** @return list<Batch> newest first, as JLC sends them */
    public function between(string $orderType, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $batches = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $data = $this->client->post(self::PATH, [
                'pageNum'         => $page,
                'pageSize'        => self::PAGE_SIZE,
                'orderType'       => $orderType,
                'createTimeStart' => self::chinaTime($from),
                'createTimeEnd'   => self::chinaTime($to),
            ]);

            if (! is_array($data)) {
                throw new JlcApiException('Batch list without data.');
            }

            foreach (is_array($data['list'] ?? null) ? $data['list'] : [] as $row) {
                if (is_array($row) && is_string($row['batchNum'] ?? null)) {
                    $batches[] = Batch::fromRow($row);
                }
            }

            if ($page >= (int) ($data['pages'] ?? 1)) {
                break;
            }
        }

        return $batches;
    }

    /** JLC reads every date as China Standard Time (GMT+8). */
    public static function chinaTime(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
    }
}

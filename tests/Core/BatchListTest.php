<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Api\Credentials;
use TaxApiJlcpcb\Core\Api\HttpResponse;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Orders\BatchList;

final class BatchListTest extends TestCase
{
    /** Shape of the response example in JLC's «Paginated Query of Batch Information by Order Type», 2026-10-21. */
    public function testReadsAllPagesInChinaTime(): void
    {
        $page = static fn (int $num, string $batch): HttpResponse => new HttpResponse(200, json_encode([
            'code' => 200, 'message' => null, 'successful' => true,
            'data' => ['pageNum' => $num, 'pageSize' => 50, 'total' => 2, 'pages' => 2,
                'list' => [['batchNum' => $batch, 'orderTypeInfos' => [['batchOrderType' => 'order_pcb', 'orderNum' => 2]]]]],
        ]));
        $transport = new ScriptedTransport([$page(1, 'W2026081409340017'), $page(2, 'W2026060418140142')]);
        $client = new JlcClient($transport, new Credentials('A', 'K', 'S'), 'https://open.example', static fn (): int => 1);

        $batches = (new BatchList($client))->between(BatchList::PCB, new DateTimeImmutable('2026-07-23 22:00:00 UTC'), new DateTimeImmutable('2026-10-21 12:00:00 UTC'));

        self::assertSame(['W2026081409340017', 'W2026060418140142'], array_map(static fn ($b): string => $b->number, $batches));
        self::assertSame(['order_pcb' => 2], $batches[0]->ordersByType);
        self::assertCount(2, $transport->sent);
        self::assertSame(
            '{"pageNum":1,"pageSize":50,"orderType":"order_pcb","createTimeStart":"2026-07-24 06:00:00","createTimeEnd":"2026-10-21 20:00:00"}',
            $transport->sent[0]->body
        );
    }
}

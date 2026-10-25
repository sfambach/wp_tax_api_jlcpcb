<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Taxmod\Core\Import\ProposalKind;
use TaxApiJlcpcb\Core\Api\Credentials;
use TaxApiJlcpcb\Core\Api\HttpResponse;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Feed\BoardTitle;
use TaxApiJlcpcb\Core\Feed\OrderFeed;
use TaxApiJlcpcb\Core\Orders\BatchList;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;

final class OrderFeedTest extends TestCase
{
    private static function list(string ...$numbers): HttpResponse
    {
        $rows = array_map(static fn (string $n): array => ['batchNum' => $n, 'orderTypeInfos' => [['batchOrderType' => 'order_pcb', 'orderNum' => 1]]], $numbers);

        return new HttpResponse(200, (string) json_encode(['code' => 200, 'data' => ['list' => $rows, 'pages' => 1]]));
    }

    public function testFirstRunReachesBackAYearAndBringsEachBatchOnce(): void
    {
        $detail = new HttpResponse(200, (string) file_get_contents(__DIR__ . '/fixtures/order-detail-v2.json'));
        // PCB list names the batch, the stencil list names it again: one detail call, one proposal.
        $transport = new ScriptedTransport([self::list('W2026092101021667'), self::list('W2026092101021667'), $detail]);
        $client = new JlcClient($transport, new Credentials('A', 'K', 'S'), 'https://open.example', static fn (): int => 1);
        $now = new DateTimeImmutable('2026-10-21 12:00', new DateTimeZone('UTC'));

        $batch = (new OrderFeed(new BatchList($client), new OrderDetailQuery($client), new DateTimeZone('Europe/Berlin')))->run(null, $now);

        self::assertCount(3, $transport->sent);
        self::assertStringContainsString('"createTimeStart":"2025-10-21 20:00:00"', $transport->sent[0]->body);
        self::assertSame($now->format(DATE_ATOM), $batch->cursor);
        self::assertCount(1, $batch->proposals);

        $proposal = $batch->proposals[0]->proposal;
        self::assertSame(['JLCPCB', 'W2026092101021667', ProposalKind::Ordered, '2026-09-20', 'USD', 'jlcpcb', 'W2026092101021667'],
            [$proposal->platform, $proposal->orderNumber, $proposal->kind, $proposal->orderDate, $proposal->currency, $proposal->source, $proposal->externalId]);
        self::assertSame('496.44', $proposal->total);
        self::assertSame('45', $proposal->fees);
        self::assertSame(['1234567890'], $proposal->trackingNumbers);
        self::assertStringStartsWith('Leiterplatte ', $proposal->lines[0]->title);
        self::assertNotNull($proposal->lines[0]->unitPrice);
        self::assertSame('Y3423', $proposal->lines[0]->articleNumber);
    }

    public function testBoardTitleReadsLikeTheMailImport(): void
    {
        self::assertSame('Leiterplatte ISA_Card', BoardTitle::of('ISA_Card_Y87_gerber.zip'));
    }
}

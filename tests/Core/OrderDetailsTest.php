<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use PHPUnit\Framework\TestCase;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;
use Taxmod\Core\Import\ProposalLine;
use TaxApiJlcpcb\Core\Api\Credentials;
use TaxApiJlcpcb\Core\Api\HttpResponse;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Enrich\PriceCompletion;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;
use TaxApiJlcpcb\Core\Orders\OrderDetails;

/** Shape of the response example of JLC's «Query Order Details by Batch Number V2», 2026-10-21. */
final class OrderDetailsTest extends TestCase
{
    private static function details(): OrderDetails
    {
        $transport = new ScriptedTransport([new HttpResponse(200, (string) file_get_contents(__DIR__ . '/fixtures/order-detail-v2.json'))]);
        $client = new JlcClient($transport, new Credentials('A', 'K', 'S'), 'https://open.example', static fn (): int => 1);
        $details = (new OrderDetailQuery($client))->batch('W2026092101021667');
        self::assertSame('https://open.example/overseas/openapi/pcb/v2/order/detail', $transport->sent[0]->url);
        self::assertSame('{"batchNum":"W2026092101021667"}', $transport->sent[0]->body);

        return $details;
    }

    public function testReadsFeesAndTrackingButNotTheAddress(): void
    {
        $details = self::details();

        self::assertSame([29.27, 45.0, '1234567890'], [$details->shippingCost, $details->customsDuty, $details->trackingNumber]);
        self::assertCount(2, $details->items);
        self::assertSame(496.44, round((float) $details->total(), 2));
        self::assertSame('2026-09-21', $details->orderDate());
        self::assertStringNotContainsString('Muster', serialize($details));
        self::assertSame('422.17', $details->moneyFields['pcbOrderInfoDataList.0.pcbOrderFeeInfo.totalFee']);
        self::assertSame('45', $details->moneyFields['tariffMoney']);
        self::assertArrayHasKey('paymentMethod', $details->moneyFields);
    }

    public function testCompletesTheMailProposalAndAddsNoLine(): void
    {
        $mail = new OrderProposal('JLCPCB', 'W2026092101021667', ProposalKind::Ordered,
            lines: [new ProposalLine('Leiterplatte ESP32_BT2PS2_SMD', 5.0), new ProposalLine('Bestückung (SMT-Auftrag SMT202609210001)', 2.0)],
            counterparty: 'JLCPCB', source: 'mail');

        $done = (new PriceCompletion())->complete($mail, self::details());

        self::assertNotNull($done);
        self::assertSame('USD', $done->currency);
        self::assertSame('29.27', $done->shippingCost);
        self::assertSame('496.44', $done->total);
        self::assertSame('2026-09-21', $done->orderDate);
        self::assertSame(['1234567890'], $done->trackingNumbers);
        self::assertCount(2, $done->lines);
        self::assertSame('84.434', $done->lines[0]->unitPrice);
        self::assertNull($done->lines[1]->unitPrice);
        self::assertSame('mail', $done->source);
    }

    public function testNothingToAddGivesNull(): void
    {
        $priced = new OrderProposal('JLCPCB', 'W2026092101021667', ProposalKind::Ordered, '2026-09-21', 'USD',
            [new ProposalLine('Leiterplatte ESP32_BT2PS2_SMD', 5.0, '84.434')], '29.27', '496.44', trackingNumbers: ['1234567890']);

        self::assertNull((new PriceCompletion())->complete($priced, self::details()));
    }

    public function testCancelledOrderIsNotMatched(): void
    {
        $mail = new OrderProposal('JLCPCB', 'W2026092101021667', ProposalKind::Ordered, '2026-09-21', 'USD',
            [new ProposalLine('Leiterplatte Old Try', 5.0)], '1', '1', trackingNumbers: ['x']);

        self::assertNull((new PriceCompletion())->complete($mail, self::details()));
    }

    public function testPricesInEuroAreLeftAlone(): void
    {
        $euro = new OrderProposal('JLCPCB', 'W2026092101021667', ProposalKind::Ordered, lines: [new ProposalLine('Leiterplatte ESP32_BT2PS2_SMD', 5.0)], total: '400');

        self::assertNull((new PriceCompletion())->complete($euro, self::details()));
    }
}

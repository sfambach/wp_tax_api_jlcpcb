<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Api\ConnectionTest;
use TaxApiJlcpcb\Core\Api\Credentials;
use TaxApiJlcpcb\Core\Api\HttpResponse;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Api\RequestSigner;

final class ClientTest extends TestCase
{
    private static function client(ScriptedTransport $transport): JlcClient
    {
        return new JlcClient($transport, new Credentials('A1', 'K1', 'S1'), 'https://open.example', static fn (): int => 1700000000);
    }

    public function testSignsExactlyTheBodyItSends(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(200, '{"code":200,"data":[{"componentCode":"C25804","componentModelEn":"0603WAF1002T5E"}]}')]);

        self::assertSame('0603WAF1002T5E', (new ConnectionTest(self::client($transport)))->run());

        $request = $transport->sent[0];
        self::assertSame('https://open.example' . ConnectionTest::DETAIL_PATH, $request->url);
        self::assertSame('{"componentCodes":["C25804"]}', $request->body);

        preg_match('/nonce="([^"]+)".*signature="([^"]+)"/', $request->headers['Authorization'], $parts);
        $expected = (new RequestSigner(new Credentials('A1', 'K1', 'S1')))->signature('POST', ConnectionTest::DETAIL_PATH, 1700000000, $parts[1], $request->body);
        self::assertSame($expected, $parts[2]);
    }

    public function testBusinessErrorCarriesMessageAndTraceId(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(200, '{"code":1001,"message":"Insufficient prepaid balance"}', 'T-42')]);

        $this->expectException(JlcApiException::class);
        $this->expectExceptionMessage('JLC error 1001: Insufficient prepaid balance [J-Trace-ID T-42]');
        self::client($transport)->post('/x', []);
    }

    public function testUnauthorizedIsAnError(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(401, '{"message":"signature verification failed"}')]);

        $this->expectExceptionMessage('HTTP 401: signature verification failed');
        self::client($transport)->post('/x', []);
    }

    public function testWrappedListIsAcceptedToo(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(200, '{"code":200,"data":{"componentDetailResponseVOList":[{"describe":"10kΩ 0603"}]}}')]);

        self::assertSame('10kΩ 0603', (new ConnectionTest(self::client($transport)))->run());
    }

    public function testEmptyAnswerIsNoSuccess(): void
    {
        $this->expectException(JlcApiException::class);
        (new ConnectionTest(self::client(new ScriptedTransport([new HttpResponse(200, '{"code":200,"data":[]}')]))))->run();
    }

    public function testSecretStaysOutOfDumps(): void
    {
        self::assertStringNotContainsString('S1', print_r(new Credentials('A1', 'K1', 'S1'), true));
    }
}

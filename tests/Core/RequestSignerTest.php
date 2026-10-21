<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Api\Credentials;
use TaxApiJlcpcb\Core\Api\RequestSigner;

final class RequestSignerTest extends TestCase
{
    /** The worked example of https://api.jlcpcb.com/docs/api-request-signature, read on 2026-10-21. */
    public function testMatchesTheExampleOfTheDocumentation(): void
    {
        $signer = new RequestSigner(new Credentials('app', 'b6713a535d56412f805afadd7e818455', 'z0BWlikshimuyiwBsH1i2qwnzMb3j3kA'));

        self::assertSame('sygwKhKBkLwHVv0c7D+a/A7JTEJjGH/kLugFKh16918=', $signer->signature(
            'POST',
            '/order/v1/createOrder',
            1625208260,
            'IZHEJYNIHYZIE8S0LLC0VWTPJVRRTO50',
            '{"goodsId":100,"quantity":52,"createdTime":"2024-03-21 10:03:20"}'
        ));
    }

    public function testHeaderNamesAppKeyNonceTimestampButNotTheSecret(): void
    {
        $header = (new RequestSigner(new Credentials('A1', 'K1', 'S3cr3t')))->header('POST', '/x', 1700000000, str_repeat('a', 32), '{}');

        self::assertStringStartsWith('JOP appid="A1",accesskey="K1",nonce="' . str_repeat('a', 32) . '",timestamp="1700000000",signature="', $header);
        self::assertStringNotContainsString('S3cr3t', $header);
    }

    public function testNonceIsThirtyTwoLettersOrDigits(): void
    {
        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', RequestSigner::nonce());
    }
}

<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core\Mail;

use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Mail\JlcpcbMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * The mail reader, moved here from Taxmod Mail (D-1006). Each expectation is what the Python importer (legacy/ in
 * Taxmod Mail) read from the same synthetic mail.
 */
final class JlcpcbMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new JlcpcbMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testJlcpcbReviewAndShippingThroughForwarding(): void
    {
        $review = self::read('j1');
        $shipped = self::read('j2');

        self::assertSame(['W202410151234567', 'ordered'], [$review['order_number'], $review['kind']]);
        self::assertSame(['Leiterplatte isa8_multi_io', 'Leiterplatte backplane-rev2'], array_column($review['lines'], 'title'));
        self::assertSame([null, null], array_column($review['lines'], 'quantity'));
        self::assertSame([5.0, 10.0], array_column($shipped['lines'], 'quantity'));
        self::assertSame(['1234567890'], $shipped['tracking']);
    }

    public function testItNamesWhatItReads(): void
    {
        self::assertNotSame([], (new JlcpcbMailParser('1.2.3'))->kinds());
        self::assertSame('1.2.3', (new JlcpcbMailParser('1.2.3'))->version());
    }
}

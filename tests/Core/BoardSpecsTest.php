<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Reorder\BoardSpecs;
use TaxApiJlcpcb\Core\Reorder\RevisionFields;

final class BoardSpecsTest extends TestCase
{
    private static function fields(): RevisionFields
    {
        return RevisionFields::fromRoles(static fn (string $role): int => crc32('n' . $role), static fn (string $role): int => crc32('f' . $role));
    }

    public function testReadsValuesAndNamesChoices(): void
    {
        $f     = self::fields();
        $specs = BoardSpecs::fromValues([
            $f->name        => ['1541 II USB Power Adapter 1'],
            $f->layers      => ['2'],
            $f->thickness   => ['1.6'],
            $f->solderMask  => ['149000110188'],
            $f->surface     => ['149000110194'],
        ], [149000110188 => 'Blau', 149000110194 => 'HASL bleifrei'], $f);

        self::assertSame(['1541 II USB Power Adapter 1', 2, '1.6', 'Blau', 'HASL bleifrei', null, null], [
            $specs->name, $specs->layers, $specs->thicknessMm, $specs->solderMaskColour, $specs->surfaceFinish, $specs->outerCopperOz, $specs->innerCopperOz,
        ]);
        self::assertFalse($specs->isEmpty());
    }

    public function testNothingKnownIsEmpty(): void
    {
        self::assertTrue(BoardSpecs::fromValues([self::fields()->name => ['X']], [], self::fields())->isEmpty());
    }
}

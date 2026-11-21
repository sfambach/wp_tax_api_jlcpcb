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

    /** Prüftor Live-Check 19 (48255): ids of constants and unit values are shown as the modeller words them, never raw. */
    public function testTheModellersWordsReplaceStoredIds(): void
    {
        $f     = self::fields();
        $specs = BoardSpecs::fromValues([
            $f->name       => ['MCEBlaster'],
            $f->thickness  => ['87928'],
            $f->solderMask => ['149000110188'],
            $f->surface    => ['149000110194'],
        ], [], $f, [$f->thickness => '1,6 mm', $f->solderMask => 'Schwarz', $f->surface => 'HASL bleifrei', $f->innerCopper => 'nie gespeichert']);

        self::assertSame(['1,6 mm', 'Schwarz', 'HASL bleifrei', null], [$specs->thicknessMm, $specs->solderMaskColour, $specs->surfaceFinish, $specs->innerCopperOz]);
    }

    public function testNothingKnownIsEmpty(): void
    {
        self::assertTrue(BoardSpecs::fromValues([self::fields()->name => ['X']], [], self::fields())->isEmpty());
    }
}

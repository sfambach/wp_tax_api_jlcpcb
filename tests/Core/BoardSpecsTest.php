<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiJlcpcb\Core\Reorder\BoardSpecs;
use TaxApiJlcpcb\Core\Reorder\RevisionFields;

final class BoardSpecsTest extends TestCase
{
    public function testReadsValuesAndNamesChoices(): void
    {
        $specs = BoardSpecs::fromValues([
            RevisionFields::NAME        => ['1541 II USB Power Adapter 1'],
            RevisionFields::LAYERS      => ['2'],
            RevisionFields::THICKNESS   => ['1.6'],
            RevisionFields::SOLDER_MASK => ['149000110188'],
            RevisionFields::SURFACE     => ['149000110194'],
        ], [149000110188 => 'Blau', 149000110194 => 'HASL bleifrei']);

        self::assertSame(['1541 II USB Power Adapter 1', 2, '1.6', 'Blau', 'HASL bleifrei', null, null], [
            $specs->name, $specs->layers, $specs->thicknessMm, $specs->solderMaskColour, $specs->surfaceFinish, $specs->outerCopperOz, $specs->innerCopperOz,
        ]);
        self::assertFalse($specs->isEmpty());
    }

    public function testNothingKnownIsEmpty(): void
    {
        self::assertTrue(BoardSpecs::fromValues([RevisionFields::NAME => ['X']], [])->isEmpty());
    }
}

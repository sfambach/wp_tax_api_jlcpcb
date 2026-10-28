<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Reorder;

/**
 * What a board revision says about how JLC made it — the values to type into JLC's quote form when ordering again
 * (D-998). Read-only; ordering and paying stay by hand.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class BoardSpecs
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $layers,
        public readonly ?string $thicknessMm,
        public readonly ?string $solderMaskColour,
        public readonly ?string $surfaceFinish,
        public readonly ?string $outerCopperOz,
        public readonly ?string $innerCopperOz,
    ) {
    }

    /**
     * @param array<int, list<string>> $values relation id → the stored values of the revision
     * @param array<int, string> $choiceLabels record id of a choice → its label
     */
    public static function fromValues(array $values, array $choiceLabels): self
    {
        $first = static fn (int $relation): ?string => isset($values[$relation][0]) && $values[$relation][0] !== '' ? $values[$relation][0] : null;
        $choice = static fn (int $relation): ?string => ($id = $first($relation)) === null ? null : ($choiceLabels[(int) $id] ?? $id);
        $layers = $first(RevisionFields::LAYERS);

        return new self(
            $first(RevisionFields::NAME) ?? '',
            $layers === null ? null : (int) $layers,
            $first(RevisionFields::THICKNESS),
            $choice(RevisionFields::SOLDER_MASK),
            $choice(RevisionFields::SURFACE),
            $first(RevisionFields::OUTER_COPPER),
            $first(RevisionFields::INNER_COPPER),
        );
    }

    public function isEmpty(): bool
    {
        return $this->layers === null && $this->thicknessMm === null && $this->solderMaskColour === null
            && $this->surfaceFinish === null && $this->outerCopperOz === null && $this->innerCopperOz === null;
    }
}

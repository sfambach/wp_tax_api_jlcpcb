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
    public static function fromValues(array $values, array $choiceLabels, RevisionFields $fields): self
    {
        $first = static fn (int $relation): ?string => isset($values[$relation][0]) && $values[$relation][0] !== '' ? $values[$relation][0] : null;
        $choice = static fn (int $relation): ?string => ($id = $first($relation)) === null ? null : ($choiceLabels[(int) $id] ?? $id);
        $layers = $first($fields->layers);

        return new self(
            $first($fields->name) ?? '',
            $layers === null ? null : (int) $layers,
            $first($fields->thickness),
            $choice($fields->solderMask),
            $choice($fields->surface),
            $first($fields->outerCopper),
            $first($fields->innerCopper),
        );
    }

    public function isEmpty(): bool
    {
        return $this->layers === null && $this->thicknessMm === null && $this->solderMaskColour === null
            && $this->surfaceFinish === null && $this->outerCopperOz === null && $this->innerCopperOz === null;
    }
}

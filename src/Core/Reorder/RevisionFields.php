<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Reorder;

/**
 * Where the modeller keeps a board revision and its manufacturing settings (D-998), named by role, never by id: the ids
 * come from the modeller's role table (D-1394).
 */
final class RevisionFields
{
    /** Role names this module uses, group → role → required. */
    public const ROLES = [
        'nodes'  => ['board_revision' => true, 'solder_mask_colours' => true, 'surface_finishes' => true],
        'fields' => [
            'label' => true, 'revision_layers' => true, 'revision_thickness' => true, 'revision_solder_mask' => true,
            'revision_surface' => true, 'revision_outer_copper' => true, 'revision_inner_copper' => true,
        ],
    ];

    /** @param list<int> $choiceNodes the choice lists behind solder mask colour and surface finish */
    public function __construct(
        public readonly int $node,
        public readonly int $name,
        public readonly int $layers,
        public readonly int $thickness,
        public readonly int $solderMask,
        public readonly int $surface,
        public readonly int $outerCopper,
        public readonly int $innerCopper,
        public readonly array $choiceNodes,
    ) {
    }

    /**
     * @param callable(string): int $node  role → node id
     * @param callable(string): int $field role → field id
     */
    public static function fromRoles(callable $node, callable $field): self
    {
        return new self(
            $node('board_revision'),
            $field('label'),
            $field('revision_layers'),
            $field('revision_thickness'),
            $field('revision_solder_mask'),
            $field('revision_surface'),
            $field('revision_outer_copper'),
            $field('revision_inner_copper'),
            [$node('solder_mask_colours'), $node('surface_finishes')],
        );
    }
}

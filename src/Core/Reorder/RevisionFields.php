<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Reorder;

/**
 * Where the modeller keeps a board revision and its manufacturing settings (D-998, fields added 2026-10-28).
 * Ids of the model, not of the code: if the model moves them, they change here.
 */
final class RevisionFields
{
    public const NODE         = 149000105262;
    public const NAME         = 149000103839;
    public const LAYERS       = 149000110169;
    public const THICKNESS    = 149000110170;
    public const SOLDER_MASK  = 149000110171;
    public const SURFACE      = 149000110172;
    public const OUTER_COPPER = 149000110173;
    public const INNER_COPPER = 149000110174;

    /** The choice lists behind solder mask colour and surface finish. */
    public const CHOICE_NODES = [149000110184, 149000110192];
}

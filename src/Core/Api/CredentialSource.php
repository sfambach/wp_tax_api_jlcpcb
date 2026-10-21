<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Where the credentials come from. Today only wp-config.php constants; the modeller's shared vault (D-951, D-952)
 * replaces the implementation, not the callers.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md
 */
interface CredentialSource
{
    /** null while any of the three values is missing */
    public function credentials(): ?Credentials;
}

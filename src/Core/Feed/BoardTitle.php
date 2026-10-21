<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Feed;

/**
 * The line title of a board, as tax_mail's JLCPCB parser writes it («Leiterplatte …», the owner's language): orders
 * from mail and from the API then read alike in the model.
 *
 * @see https://github.com/sfambach/wp_tax_mail/blob/main/src/Core/Parser/Shop/Jlcpcb.php
 */
final class BoardTitle
{
    public static function of(string $gerberName): string
    {
        $t = preg_replace('/(\.{3}|…)$/u', '', trim($gerberName)) ?? $gerberName;
        $t = preg_replace('/\.(zip|rar|7z)$/i', '', $t) ?? $t;
        $t = preg_replace('/\((replacefile|Reorde\w*|-返\w*)\)?$/u', '', $t) ?? $t;
        $t = preg_replace('/_Y\d+/', '', $t) ?? $t;
        $t = trim(preg_replace('/[_ ]?gerber/i', '', $t) ?? $t, ' _-(');

        return $t !== '' ? 'Leiterplatte ' . $t : sprintf('Leiterplatte (Datei %s)', trim($gerberName));
    }
}

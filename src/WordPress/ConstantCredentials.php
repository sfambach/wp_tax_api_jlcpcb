<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Api\CredentialSource;
use TaxApiJlcpcb\Core\Api\Credentials;

/**
 * Reads the app's keys from wp-config.php. Nothing is stored by this plugin, so nothing needs sealing until the
 * modeller's vault (D-951) offers a form.
 */
final class ConstantCredentials implements CredentialSource
{
    public const APP_ID     = 'TAXJLCPCB_APP_ID';
    public const ACCESS_KEY = 'TAXJLCPCB_ACCESS_KEY';
    public const SECRET_KEY = 'TAXJLCPCB_SECRET_KEY';
    public const BASE_URL   = 'TAXJLCPCB_BASE_URL';

    public function credentials(): ?Credentials
    {
        $credentials = new Credentials(self::value(self::APP_ID), self::value(self::ACCESS_KEY), self::value(self::SECRET_KEY));

        return $credentials->isComplete() ? $credentials : null;
    }

    /** @return list<string> the constants still undefined or empty */
    public function missing(): array
    {
        return array_values(array_filter([self::APP_ID, self::ACCESS_KEY, self::SECRET_KEY], static fn (string $name): bool => self::value($name) === ''));
    }

    public static function baseUrl(): ?string
    {
        $url = self::value(self::BASE_URL);

        return $url === '' ? null : $url;
    }

    private static function value(string $name): string
    {
        return defined($name) && is_string(constant($name)) ? trim(constant($name)) : '';
    }
}

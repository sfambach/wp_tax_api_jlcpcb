<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use Taxmod\Core\Exception\SecretDoesNotOpen;
use Taxmod\WordPress\Import\Vault;
use TaxApiJlcpcb\Core\Api\CredentialSource;
use TaxApiJlcpcb\Core\Api\Credentials;

/**
 * The keys from the «JLCPCB» tab, the SecretKey sealed in the modeller's vault (D-951). Constants in wp-config.php
 * go first (D-944); without the vault there is no form, only the constants.
 */
final class StoredCredentials implements CredentialSource
{
    public const OPTION        = 'taxmod_jlcpcb_keys';
    public const VAULT_CONTEXT = 'jlcpcb';

    public function __construct(private readonly ConstantCredentials $constants = new ConstantCredentials())
    {
    }

    public static function vaultAvailable(): bool
    {
        return class_exists(Vault::class) && Vault::API >= 1;
    }

    public function fromConstants(): bool
    {
        return $this->constants->credentials() !== null;
    }

    public function credentials(): ?Credentials
    {
        return $this->constants->credentials() ?? $this->stored();
    }

    /** @return array{app_id: string, access_key: string, has_secret: bool} what the form may show again */
    public function shown(): array
    {
        $stored = self::option();

        return ['app_id' => $stored['app_id'], 'access_key' => $stored['access_key'], 'has_secret' => $stored['secret'] !== ''];
    }

    /** An empty secret keeps the stored one. Caller has checked capability and nonce and sanitized. */
    public function save(string $appId, string $accessKey, #[\SensitiveParameter] string $secret): void
    {
        if (! self::vaultAvailable()) {
            return;
        }

        $stored = self::option();
        update_option(self::OPTION, [
            'app_id'     => $appId,
            'access_key' => $accessKey,
            'secret'     => $secret === '' ? $stored['secret'] : Vault::for(self::VAULT_CONTEXT)->seal($secret),
        ], false);
    }

    /** true when a secret is stored but no longer opens — the salts in wp-config.php changed */
    /** Deletes the stored keys; keys in wp-config.php stay, they are not this page's to delete. */
    public function forget(): void
    {
        delete_option(self::OPTION);
    }

    public function secretBroken(): bool
    {
        $stored = self::option();

        return $stored['secret'] !== '' && self::vaultAvailable() && $this->open($stored['secret']) === null;
    }

    private function stored(): ?Credentials
    {
        $stored = self::option();

        if ($stored['secret'] === '' || ! self::vaultAvailable()) {
            return null;
        }

        $credentials = new Credentials($stored['app_id'], $stored['access_key'], $this->open($stored['secret']) ?? '');

        return $credentials->isComplete() ? $credentials : null;
    }

    private function open(string $sealed): ?string
    {
        try {
            return Vault::for(self::VAULT_CONTEXT)->open($sealed);
        } catch (SecretDoesNotOpen) {
            return null;
        }
    }

    /** @return array{app_id: string, access_key: string, secret: string} */
    private static function option(): array
    {
        $value = get_option(self::OPTION);
        $value = is_array($value) ? $value : [];
        $text = static fn (string $key): string => is_string($value[$key] ?? null) ? $value[$key] : '';

        return ['app_id' => $text('app_id'), 'access_key' => $text('access_key'), 'secret' => $text('secret')];
    }
}

<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * The three values of one JLC API app: App ID, AccessKey (identifier) and SecretKey (signs, never sent).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class Credentials
{
    public function __construct(
        public readonly string $appId,
        public readonly string $accessKey,
        #[\SensitiveParameter] public readonly string $secretKey,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->appId !== '' && $this->accessKey !== '' && $this->secretKey !== '';
    }

    /** Keeps the secret out of dumps and logs. */
    public function __debugInfo(): array
    {
        return ['appId' => $this->appId, 'accessKey' => $this->accessKey, 'secretKey' => '***'];
    }
}

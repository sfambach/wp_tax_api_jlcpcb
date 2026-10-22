<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * For the probe only: every scalar field of a raw answer with its path — to see what JLC really sends beside the
 * documented fields (money, board specs, build time, ship date). The address branch is skipped as a whole.
 */
final class ProbeFields
{
    /**
     * @param array<array-key, mixed> $data the `data` member of the answer
     * @return array<string, string> path => value
     */
    public static function from(array $data, string $prefix = ''): array
    {
        $found = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if ($key === 'orderAddress' || $key === 'shippingAddress') {
                continue;
            }

            if (is_array($value)) {
                $found += self::from($value, $path);
            } elseif (is_scalar($value) || $value === null) {
                $found[$path] = $value === null ? 'null' : (string) $value;
            }
        }

        return $found;
    }
}

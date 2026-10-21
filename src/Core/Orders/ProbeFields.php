<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * For the probe only: every field of a raw answer whose name speaks of money, currency, a file or an image, with its
 * path — to see what JLC really sends beside the documented fields. The address branch is skipped as a whole.
 */
final class ProbeFields
{
    private const NAME = '/money|fee|price|cost|currenc|rate|amount|total|pay|url|img|image|pic|photo|thumb|file|path/i';

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
            } elseif (is_string($key) && preg_match(self::NAME, $key) === 1 && (is_scalar($value) || $value === null)) {
                $found[$path] = $value === null ? 'null' : (string) $value;
            }
        }

        return $found;
    }
}

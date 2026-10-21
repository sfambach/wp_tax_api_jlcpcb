<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

/**
 * For the probe only: every field of a raw answer whose name speaks of money or currency, with its path — to see what
 * JLC really sends beside the documented fields. The address branch is skipped as a whole.
 */
final class MoneyFields
{
    private const NAME = '/money|fee|price|cost|currenc|rate|amount|total|pay/i';

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

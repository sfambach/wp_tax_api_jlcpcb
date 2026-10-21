<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Api;

/**
 * Proves keys and signature with a read that changes nothing: the public detail of one well-known component.
 * The path comes from a community client, not from JLC's own documentation (unverified until his first test).
 */
final class ConnectionTest
{
    public const DETAIL_PATH = '/overseas/openapi/component/getComponentDetailByCode';

    /** A 0603 10 kΩ resistor of JLC's basic library — present as long as JLC assembles boards. */
    public const PROBE_CODE = 'C25804';

    public function __construct(private readonly JlcClient $client)
    {
    }

    /** @return string the component's model or description, as proof of a real answer */
    public function run(): string
    {
        $data = $this->client->post(self::DETAIL_PATH, ['componentCodes' => [self::PROBE_CODE]]);

        if (is_array($data) && isset($data['componentDetailResponseVOList']) && is_array($data['componentDetailResponseVOList'])) {
            $data = $data['componentDetailResponseVOList'];
        }

        $first = is_array($data) ? ($data[0] ?? null) : null;

        if (! is_array($first)) {
            throw new JlcApiException('Signed call accepted, but no component came back.');
        }

        foreach (['componentModelEn', 'componentModel', 'describe', 'componentName', 'componentCode'] as $field) {
            if (is_string($first[$field] ?? null) && $first[$field] !== '') {
                return $first[$field];
            }
        }

        return self::PROBE_CODE;
    }
}

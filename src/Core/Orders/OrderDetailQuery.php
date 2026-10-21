<?php declare(strict_types=1);

namespace TaxApiJlcpcb\Core\Orders;

use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;

/**
 * «Query Order Details by Batch Number V2» of the PCB API: the details of one batch `W…`, from the owner's console docs (2026-10-21).
 */
final class OrderDetailQuery
{
    public const PATH = '/overseas/openapi/pcb/v2/order/detail';

    public function __construct(private readonly JlcClient $client)
    {
    }

    public function batch(string $batchNumber): OrderDetails
    {
        $data = $this->client->post(self::PATH, ['batchNum' => $batchNumber]);

        if (! is_array($data)) {
            throw new JlcApiException(sprintf('No details for %s.', $batchNumber));
        }

        return OrderDetails::fromData($batchNumber, $data);
    }
}

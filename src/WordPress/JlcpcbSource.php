<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use Taxmod\Core\Import\EnrichingSource;
use Taxmod\Core\Import\OrderProposal;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Enrich\PriceCompletion;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;

/**
 * JLCPCB as a source of the modeller's inbox (D-952, D-953): it completes the JLCPCB proposals of other sources with
 * prices and creates nothing. A failed call leaves the proposal as it is; the last error shows in the tab.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md
 */
final class JlcpcbSource implements EnrichingSource
{
    public const PLATFORM     = 'JLCPCB';
    public const ERROR_OPTION = 'taxmod_jlcpcb_last_enrich_error';

    public function key(): string
    {
        return Plugin::TAB;
    }

    public function label(): string
    {
        return __('JLCPCB', 'taxapijlcpcb');
    }

    public function enriches(): array
    {
        return [self::PLATFORM];
    }

    public function enrich(OrderProposal $proposal): ?OrderProposal
    {
        $credentials = (new StoredCredentials())->credentials();

        if ($credentials === null || preg_match('/^W\d{14,16}$/', trim($proposal->orderNumber)) !== 1) {
            return null;
        }

        try {
            $client = new JlcClient(new WpHttpTransport(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL);
            $details = (new OrderDetailQuery($client))->batch(trim($proposal->orderNumber));

            return (new PriceCompletion(wp_timezone()))->complete($proposal, $details);
        } catch (JlcApiException $failure) {
            update_option(self::ERROR_OPTION, ['at' => current_time('mysql'), 'order' => $proposal->orderNumber, 'message' => $failure->getMessage()], false);

            return null;
        }
    }
}

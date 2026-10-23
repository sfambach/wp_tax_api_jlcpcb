<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use DateTimeImmutable;
use Taxmod\Core\Import\EnrichingSource;
use Taxmod\Core\Import\FetchBatch;
use Taxmod\Core\Import\FetchingSource;
use Taxmod\Core\Import\OrderProposal;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Enrich\PriceCompletion;
use TaxApiJlcpcb\Core\Feed\OrderFeed;
use TaxApiJlcpcb\Core\Orders\BatchList;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;

/**
 * JLCPCB as a source of the modeller's inbox (D-952): it brings the JLCPCB orders itself — the API is the truth, mail
 * only the status (D-965) — and still completes JLCPCB proposals of other sources with prices. A failed completion
 * leaves the proposal as it is and shows in the tab; a failed fetch is thrown, the inbox shows it.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md
 */
final class JlcpcbSource implements FetchingSource, EnrichingSource
{
    public const PLATFORM     = OrderFeed::PLATFORM;
    public const ERROR_OPTION = 'taxmod_jlcpcb_last_enrich_error';

    public function key(): string
    {
        return Plugin::TAB;
    }

    public function label(): string
    {
        return __('JLCPCB', 'taxapijlcpcb');
    }

    /**
     * The default only: the modeller lets him choose per module (D-981). Once a day, JLC less often than eBay (his word
     * 2026-10-23: «jlc brauchen wir nicht so oft wie ebay»); without keys nothing to fetch.
     */
    public function interval(): int
    {
        return self::client() === null ? 0 : DAY_IN_SECONDS;
    }

    public function fetch(?string $cursor): FetchBatch
    {
        $client = self::client() ?? throw new JlcApiException(__('The JLCPCB keys are missing.', 'taxapijlcpcb'));

        return (new OrderFeed(new BatchList($client), new OrderDetailQuery($client), wp_timezone()))->run($cursor, new DateTimeImmutable('now'));
    }

    public function enriches(): array
    {
        return [self::PLATFORM];
    }

    public function enrich(OrderProposal $proposal): ?OrderProposal
    {
        $client = self::client();

        if ($client === null || preg_match('/^W\d{14,16}$/', trim($proposal->orderNumber)) !== 1) {
            return null;
        }

        try {
            $details = (new OrderDetailQuery($client))->batch(trim($proposal->orderNumber));

            return (new PriceCompletion(wp_timezone()))->complete($proposal, $details);
        } catch (JlcApiException $failure) {
            update_option(self::ERROR_OPTION, ['at' => current_time('mysql'), 'order' => $proposal->orderNumber, 'message' => $failure->getMessage()], false);

            return null;
        }
    }

    private static function client(): ?JlcClient
    {
        $credentials = (new StoredCredentials())->credentials();

        return $credentials === null ? null : new JlcClient(new WpHttpTransport(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL);
    }
}

<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Api\ConnectionTest;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Orders\BatchList;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;

/**
 * The boundary: the «JLCPCB» tab of the modeller's Configuration page (D-945) and the connection test. It wires
 * WordPress to the core and decides nothing.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class Plugin
{
    public const VERSION    = '0.1.2';
    public const CAPABILITY = 'manage_options';
    public const TAB        = 'jlcpcb';

    /** Messages for the next page view of one user, after a redirect. */
    private const NOTICES = 'taxapijlcpcb_notices_';

    public static function boot(): void
    {
        $plugin = new self();

        add_filter('taxmod_config_tabs', $plugin->registerTab(...));
        add_action('admin_post_taxapijlcpcb_check', $plugin->handleCheck(...));
        add_action('admin_post_taxapijlcpcb_list', $plugin->handleList(...));
        add_action('admin_post_taxapijlcpcb_detail', $plugin->handleDetail(...));

        // The inbox of the modeller 0.1.35 or later; without it the module only tests and lists. Checked when the filter
        // runs, not here: this file loads before the modeller's (alphabetical order), when its classes are not known yet.
        add_filter('taxmod_import_sources', static fn (array $sources): array => self::hasInbox() ? [...$sources, new JlcpcbSource()] : $sources);
        // The API brings new JLCPCB orders and their prices; mail is left the status (D-965, D-961). The status row stays
        // open so the tracking number of the API counts as well; the table keys platforms in lower case.
        add_filter('taxmod_import_duties', static function (array $rows): array {
            if (! self::hasInbox()) {
                return $rows;
            }

            $platform = mb_strtolower(JlcpcbSource::PLATFORM);
            $rows[$platform] = array_replace($rows[$platform] ?? [], ['orders' => self::TAB, 'prices' => self::TAB]);

            return $rows;
        });
        add_action('admin_notices', $plugin->noticeWithoutTabs(...));
    }

    /** The modeller's developer mode shows the module version in the tab title. */
    private static function inDeveloperMode(): bool
    {
        return class_exists(\Taxmod\WordPress\Admin\SettingsScreen::class)
            && method_exists(\Taxmod\WordPress\Admin\SettingsScreen::class, 'inDeveloperMode')
            && \Taxmod\WordPress\Admin\SettingsScreen::inDeveloperMode();
    }

    private static function hasInbox(): bool
    {
        return interface_exists(\Taxmod\Core\Import\FetchingSource::class) && interface_exists(\Taxmod\Core\Import\EnrichingSource::class);
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function registerTab(array $tabs): array
    {
        $tabs[self::TAB] = [
            'title'  => __('JLCPCB', 'taxapijlcpcb') . (self::inDeveloperMode() ? ' ' . self::VERSION : ''),
            'render' => fn (): string => (new SettingsScreen(new StoredCredentials()))->fields($this->takeNotices()),
            'save'   => static fn (array $post): bool => self::saveKeys($post),
            'guide'  => static fn (): string => SettingsScreen::guide(),
        ];

        return $tabs;
    }

    /** Without the modeller's tabs there is no place for the settings; say so instead of adding a menu. */
    public function noticeWithoutTabs(): void
    {
        if (defined('Taxmod\WordPress\Admin\SettingsScreen::TABS_FILTER') || ! current_user_can(self::CAPABILITY)) {
            return;
        }

        print '<div class="notice notice-warning"><p><strong>Taxmod JLCPCB:</strong> '
            . esc_html__('needs the Taxonomy Modeller with configuration tabs (0.1.27 or later).', 'taxapijlcpcb') . '</p></div>';
    }

    /**
     * Validates and sanitizes the key fields; the modeller has checked capability and nonce (D-945).
     *
     * @param array<string, mixed> $post already unslashed
     */
    private static function saveKeys(array $post): bool
    {
        $source = new StoredCredentials();

        if ($source->fromConstants() || ! StoredCredentials::vaultAvailable()) {
            return true;
        }

        $field = static fn (string $name): string => is_string($post[$name] ?? null) ? trim(sanitize_text_field($post[$name])) : '';
        $source->save($field('taxapijlcpcb_app_id'), $field('taxapijlcpcb_access_key'), $field('taxapijlcpcb_secret_key'));

        return $source->credentials() !== null;
    }

    public static function settingsUrl(): string
    {
        return admin_url('admin.php?page=taxmod-settings&tab=' . self::TAB);
    }

    /** Days the probe list looks back. */
    public const LIST_DAYS = 365;

    /**
     * Probe of step 2: lists the PCB and stencil batches of the last year, numbers only, and shows them in the tab.
     * Nothing is written to the model.
     */
    public function handleList(): void
    {
        $client = $this->clientOrBack('taxapijlcpcb_list');

        try {
            $to = new \DateTimeImmutable('now');
            $from = $to->modify('-' . self::LIST_DAYS . ' days');
            $rows = [];

            foreach ([BatchList::PCB, BatchList::STENCIL] as $type) {
                foreach ((new BatchList($client))->between($type, $from, $to) as $batch) {
                    $rows[$batch->number] = ($rows[$batch->number] ?? []) + $batch->ordersByType;
                }
            }

            krsort($rows);
            update_option('taxmod_jlcpcb_last_list', ['at' => current_time('mysql'), 'batches' => $rows], false);
            $this->notice(true, sprintf(
                /* translators: 1: number of batches, 2: days */
                __('%1$d orders found in the last %2$d days.', 'taxapijlcpcb'),
                count($rows),
                self::LIST_DAYS
            ));
        } catch (JlcApiException $failure) {
            $this->notice(false, __('Listing failed:', 'taxapijlcpcb') . ' ' . $failure->getMessage());
        }

        $this->back();
    }

    /** Probe: the details of one listed batch, shown in the tab without the shipping address. */
    public function handleDetail(): void
    {
        $batch = isset($_GET['batch']) && is_string($_GET['batch']) ? sanitize_text_field(wp_unslash($_GET['batch'])) : '';
        $client = $this->clientOrBack('taxapijlcpcb_detail_' . $batch);

        if (preg_match('/^W\d{14,16}$/', $batch) !== 1) {
            $this->notice(false, __('Not a JLCPCB order number.', 'taxapijlcpcb'));
            $this->back();
        }

        try {
            $details = (new OrderDetailQuery($client))->batch($batch);
            update_option('taxmod_jlcpcb_last_detail', [
                'at'       => current_time('mysql'),
                'batch'    => $details->batchNumber,
                'shipping' => $details->shippingMethod,
                'carriage' => $details->shippingCost,
                'customs'  => $details->customsDuty,
                'total'    => $details->total(),
                'tracking' => $details->trackingNumber,
                'stencils' => $details->stencilOrders,
                'money'    => $details->moneyFields,
                'items'    => array_map(static fn ($item): array => [
                    'file' => $item->gerberName, 'count' => $item->count, 'price' => $item->fee, 'status' => $item->status,
                ], $details->items),
            ], false);
            $this->notice(true, sprintf(
                /* translators: %s: order number */
                __('Details of %s read.', 'taxapijlcpcb'),
                $batch
            ));
        } catch (JlcApiException $failure) {
            $this->notice(false, __('Reading the details failed:', 'taxapijlcpcb') . ' ' . $failure->getMessage());
        }

        $this->back();
    }

    /** CD-5: capability, nonce, then act; the result goes back as a notice. */
    public function handleCheck(): void
    {
        $client = $this->clientOrBack('taxapijlcpcb_check');

        try {
            $component = (new ConnectionTest($client))->run();
            $this->notice(true, sprintf(
                /* translators: 1: component code, 2: its model or description */
                __('Connection works: JLC answered the signed request for %1$s (%2$s).', 'taxapijlcpcb'),
                ConnectionTest::PROBE_CODE,
                $component
            ));
            update_option('taxmod_jlcpcb_last_check', ['ok' => true, 'at' => current_time('mysql'), 'message' => ''], false);
        } catch (JlcApiException $failure) {
            $this->notice(false, __('Connection failed:', 'taxapijlcpcb') . ' ' . $failure->getMessage());
            update_option('taxmod_jlcpcb_last_check', ['ok' => false, 'at' => current_time('mysql'), 'message' => $failure->getMessage()], false);
        }

        $this->back();
    }

    private function clientOrBack(string $action): JlcClient
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You may not use the JLCPCB connection.', 'taxapijlcpcb'), 403);
        }

        check_admin_referer($action);
        $credentials = (new StoredCredentials())->credentials();

        if ($credentials === null) {
            $this->notice(false, __('The keys are missing — enter and save them first, or define them in wp-config.php.', 'taxapijlcpcb'));
            $this->back();
        }

        return new JlcClient(new WpHttpTransport(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL);
    }

    private function back(): never
    {
        wp_safe_redirect(self::settingsUrl());
        exit;
    }

    private function notice(bool $ok, string $text): void
    {
        $key = self::NOTICES . get_current_user_id();
        $notices = get_transient($key);
        $notices = is_array($notices) ? $notices : [];
        $notices[] = ['ok' => $ok, 'text' => $text];
        set_transient($key, $notices, 10 * MINUTE_IN_SECONDS);
    }

    /** @return list<array{ok: bool, text: string}> */
    private function takeNotices(): array
    {
        $key = self::NOTICES . get_current_user_id();
        $notices = get_transient($key);
        delete_transient($key);

        return is_array($notices)
            ? array_values(array_map(static fn (mixed $n): array => ['ok' => (bool) ($n['ok'] ?? false), 'text' => (string) ($n['text'] ?? '')], $notices))
            : [];
    }
}

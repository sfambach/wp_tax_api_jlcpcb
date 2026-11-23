<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use Taxmod\WordPress\Admin\ListPage;
use TaxApiJlcpcb\Core\Api\ConnectionTest;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;
use TaxApiJlcpcb\Core\Mail\JlcpcbMailParser;
use TaxApiJlcpcb\Core\Orders\BatchList;
use TaxApiJlcpcb\Core\Orders\OrderDetailQuery;
use Taxmod\Core\Import\Mail\ShopMailParser;

/**
 * The boundary: the «JLCPCB» tab of the modeller's Configuration page (D-945) and the connection test. It wires
 * WordPress to the core and decides nothing.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class Plugin
{
    public const VERSION    = '0.1.24';
    /** The connection is set up in the configuration (D-1585): the modeller's right «configure». */
    public const CAPABILITY = \Taxmod\WordPress\Rights::CONFIGURE;
    public const TAB        = 'jlcpcb';

    /** Messages for the next page view of one user, after a redirect. */
    private const NOTICES = 'taxapijlcpcb_notices_';

    /**
     * Whether the modeller is 0.2.237 or later (D-1409, D-1415; ListPage::button/money, D-1500) — asked by version, not by loading its classes: method_exists() on
     * ListPage compiled the 67 KB list page on every page view (D-1187; review 2026-11-17, Befund 6).
     */
    private static function modellerFits(): bool
    {
        return class_exists(\Taxmod\WordPress\Plugin::class, false) && version_compare(\Taxmod\WordPress\Plugin::VERSION, '0.2.237', '>=');
    }

    public static function boot(): void
    {
        $plugin = new self();

        // D-1500: the German texts in languages/ — WordPress finds a plugin's own folder only when asked.
        add_action('init', static fn () => load_plugin_textdomain('taxapijlcpcb', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-jlcpcb.php')) . '/languages'), 1);

        // D-1409, D-1415: this release stands on the modeller 0.2.237 (rights table D-1585; shared parser frame, HTTP block, ListPage::acting; button/money D-1500); an older one says so.
        add_action('admin_notices', static function (): void {
            if (class_exists(\Taxmod\WordPress\Plugin::class, false) && ! self::modellerFits()) {
                print '<div class="notice notice-warning"><p><strong>Taxmod JLCPCB:</strong> ' . esc_html__('needs the Taxonomy Modeller 0.2.237 or later.', 'taxapijlcpcb') . '</p></div>';
            }
        });
        add_filter('taxmod_config_tabs', $plugin->registerTab(...));
        // ListPage::acting belongs to the modeller, which loads after this file (alphabetical order): hooked once every plugin is.
        add_action('plugins_loaded', static function () use ($plugin): void {
            if (! self::modellerFits() || ! \Taxmod\WordPress\Plugin::handlesAdminActions()) {
                return;
            }

            add_action('admin_post_taxapijlcpcb_list', ListPage::acting($plugin->handleList(...), self::settingsUrl()));
            add_action('admin_post_taxapijlcpcb_detail', ListPage::acting($plugin->handleDetail(...), self::settingsUrl()));
        });

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
        // ⚠️ *D-1006: JLCPCB's mails are read here, not in Taxmod Mail, which only fetches them (needs modeller 0.1.81).*
        add_filter('taxmail_parsers', static fn (array $parsers): array => ($reader = self::mailReader()) === null ? $parsers : [...$parsers, $reader]);
        add_action('admin_notices', $plugin->noticeWithoutTabs(...));
        ReorderPage::boot();
    }

    /** The reader of JLCPCB's mails, once; null while the modeller lacks the mail contract (before 0.1.81). */
    public static function mailReader(): ?JlcpcbMailParser
    {
        static $reader = null;

        return $reader ??= class_exists(ShopMailParser::class) ? new JlcpcbMailParser(self::VERSION) : null;
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
            'title'   => __('JLCPCB', 'taxapijlcpcb'),
            'version' => self::VERSION,
            // D-981: the modeller orders the page (Access, Fetching, Settings) and draws Save, Test, Delete and the interval.
            'source'      => self::TAB,
            'credentials' => static fn (): string => (new SettingsScreen(new StoredCredentials()))->credentials(),
            'test'        => static fn (): string => self::testConnection(),
            'forget'      => static fn () => (new StoredCredentials())->forget(),
            'render'      => fn (): string => (new SettingsScreen(new StoredCredentials()))->fields($this->takeNotices()),
            'save'        => static fn (array $post): bool => self::saveKeys($post),
            'guide'       => static fn (): string => SettingsScreen::guide(),
            'reader'      => self::mailReader(),
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
                'money'    => $details->probeFields,
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

    /** «Test connection» of the modeller (D-981), run after saving; capability and nonce are checked there. Returns one sentence. */
    private static function testConnection(): string
    {
        $credentials = (new StoredCredentials())->credentials();

        if ($credentials === null) {
            return __('The keys are missing — enter and save them first, or define them in wp-config.php.', 'taxapijlcpcb');
        }

        try {
            $component = (new ConnectionTest(new JlcClient(ApiTransport::make(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL)))->run();
            update_option('taxmod_jlcpcb_last_check', ['ok' => true, 'at' => current_time('mysql'), 'message' => ''], false);

            return sprintf(
                /* translators: 1: component code, 2: its model or description */
                __('Connection works: JLC answered the signed request for %1$s (%2$s).', 'taxapijlcpcb'),
                ConnectionTest::PROBE_CODE,
                $component
            );
        } catch (JlcApiException $failure) {
            update_option('taxmod_jlcpcb_last_check', ['ok' => false, 'at' => current_time('mysql'), 'message' => $failure->getMessage()], false);

            return __('Connection failed:', 'taxapijlcpcb') . ' ' . $failure->getMessage();
        }
    }

    private function clientOrBack(string $action): JlcClient
    {
        if (! current_user_can(self::CAPABILITY)) {
            self::refuse(__('You may not use the JLCPCB connection.', 'taxapijlcpcb'));
        }

        ListPage::nonce($action);
        $credentials = (new StoredCredentials())->credentials();

        if ($credentials === null) {
            $this->notice(false, __('The keys are missing — enter and save them first, or define them in wp-config.php.', 'taxapijlcpcb'));
            $this->back();
        }

        return new JlcClient(ApiTransport::make(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL);
    }

    /**
     * The handler as an act (D-1254): an exception becomes a message in the tab's message field with the way back, never an
     * empty page. ListPage is named only inside the handler, so the boot does not compile it (D-1244).
     */

    /** A refusal as a message on the tab instead of a bare error page (D-1254). */
    private static function refuse(string $message): never
    {
        ListPage::fail($message, self::settingsUrl());
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

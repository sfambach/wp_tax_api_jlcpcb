<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Api\ConnectionTest;
use TaxApiJlcpcb\Core\Api\JlcApiException;
use TaxApiJlcpcb\Core\Api\JlcClient;

/**
 * The boundary: the «JLCPCB» tab of the modeller's Configuration page (D-945) and the connection test. It wires
 * WordPress to the core and decides nothing.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class Plugin
{
    public const VERSION    = '0.0.1';
    public const CAPABILITY = 'manage_options';
    public const TAB        = 'jlcpcb';

    /** Messages for the next page view of one user, after a redirect. */
    private const NOTICES = 'taxapijlcpcb_notices_';

    public static function boot(): void
    {
        $plugin = new self();

        add_filter('taxmod_config_tabs', $plugin->registerTab(...));
        add_action('admin_post_taxapijlcpcb_check', $plugin->handleCheck(...));
        add_action('admin_notices', $plugin->noticeWithoutTabs(...));
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function registerTab(array $tabs): array
    {
        $tabs[self::TAB] = [
            'title'  => __('JLCPCB', 'taxapijlcpcb'),
            'render' => fn (): string => (new SettingsScreen(new ConstantCredentials()))->fields($this->takeNotices()),
            // Nothing to save: the keys live in wp-config.php.
            'save'   => static fn (array $post): bool => true,
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

    public static function settingsUrl(): string
    {
        return admin_url('admin.php?page=taxmod-settings&tab=' . self::TAB);
    }

    /** CD-5: capability, nonce, then act; the result goes back as a notice. */
    public function handleCheck(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You may not test the JLCPCB connection.', 'taxapijlcpcb'), 403);
        }

        check_admin_referer('taxapijlcpcb_check');

        $credentials = (new ConstantCredentials())->credentials();

        if ($credentials === null) {
            $this->notice(false, __('The keys are missing in wp-config.php.', 'taxapijlcpcb'));
            $this->back();
        }

        try {
            $client = new JlcClient(new WpHttpTransport(), $credentials, ConstantCredentials::baseUrl() ?? JlcClient::BASE_URL);
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

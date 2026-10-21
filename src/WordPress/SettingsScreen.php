<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

/**
 * The «JLCPCB» tab body: which keys are set, the connection test and its last result. Returns its HTML (CD-8).
 * The secret is never shown, not even in part.
 */
final class SettingsScreen
{
    public function __construct(private readonly ConstantCredentials $source)
    {
    }

    /** @param list<array{ok: bool, text: string}> $notices */
    public function fields(array $notices): string
    {
        $html = '';

        foreach ($notices as $notice) {
            $html .= '<div class="notice ' . ($notice['ok'] ? 'notice-success' : 'notice-error') . ' inline"><p>'
                . esc_html($notice['text']) . '</p></div>';
        }

        $html .= '<h2>' . esc_html__('1 · App keys', 'taxapijlcpcb') . '</h2>';
        $credentials = $this->source->credentials();

        if ($credentials === null) {
            $html .= '<p>' . esc_html__('From your app in the JLC API console (api.jlcpcb.com). Define them in wp-config.php — never post them in a chat:', 'taxapijlcpcb') . '</p>'
                . '<pre>' . esc_html("define('TAXJLCPCB_APP_ID', '…');\ndefine('TAXJLCPCB_ACCESS_KEY', '…');\ndefine('TAXJLCPCB_SECRET_KEY', '…');") . '</pre>'
                . '<p>' . esc_html(sprintf(
                    /* translators: %s: list of constant names */
                    __('Missing: %s', 'taxapijlcpcb'),
                    implode(', ', $this->source->missing())
                )) . '</p>';
        } else {
            $html .= '<p>' . esc_html(sprintf(
                /* translators: 1: app id, 2: access key */
                __('Set in wp-config.php: App ID %1$s, AccessKey %2$s, SecretKey set.', 'taxapijlcpcb'),
                $credentials->appId,
                $credentials->accessKey
            )) . '</p>';
        }

        $html .= '<h2>' . esc_html__('2 · Connection', 'taxapijlcpcb') . '</h2>';
        $last = get_option('taxmod_jlcpcb_last_check');

        if (is_array($last) && isset($last['at'])) {
            $html .= '<p>' . esc_html(sprintf(
                /* translators: 1: date, 2: ok or the error */
                __('Last test %1$s: %2$s', 'taxapijlcpcb'),
                (string) $last['at'],
                ! empty($last['ok']) ? __('works', 'taxapijlcpcb') : (string) ($last['message'] ?? '')
            )) . '</p>';
        }

        if ($credentials !== null) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=taxapijlcpcb_check'), 'taxapijlcpcb_check');
            $html .= '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html__('Test connection', 'taxapijlcpcb') . '</a></p>';
        }

        return $html . '<p class="description">' . esc_html__('The IP whitelist of the app may stay empty. If you fill it, add the outgoing IP of this web server.', 'taxapijlcpcb') . '</p>';
    }
}

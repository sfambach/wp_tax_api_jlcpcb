<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

/**
 * The «JLCPCB» tab body: the keys, the connection test and its last result. Returns its HTML (CD-8). The modeller
 * wraps it in its own form, so the fields carry no form tag and the test is a nonce link. The secret is never shown.
 */
final class SettingsScreen
{
    public function __construct(private readonly StoredCredentials $source)
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

        if ($this->source->fromConstants()) {
            $html .= '<p>' . esc_html(sprintf(
                /* translators: 1: app id, 2: access key */
                __('Set in wp-config.php: App ID %1$s, AccessKey %2$s, SecretKey set. These take precedence over the fields.', 'taxapijlcpcb'),
                $credentials?->appId ?? '',
                $credentials?->accessKey ?? ''
            )) . '</p>';
        } elseif (StoredCredentials::vaultAvailable()) {
            $html .= $this->keyFields();
        } else {
            $html .= '<p>' . esc_html__('This Taxonomy Modeller has no vault yet (0.1.31 or later), so the keys can only be defined in wp-config.php:', 'taxapijlcpcb') . '</p>'
                . '<pre>' . esc_html("define('TAXJLCPCB_APP_ID', '…');\ndefine('TAXJLCPCB_ACCESS_KEY', '…');\ndefine('TAXJLCPCB_SECRET_KEY', '…');") . '</pre>';
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

    /** The secret goes in, never out; an empty field keeps the stored one. */
    private function keyFields(): string
    {
        $shown = $this->source->shown();
        $html = '<p>' . esc_html__('From your app in the JLC API console (api.jlcpcb.com). The SecretKey is stored encrypted. Alternatively define TAXJLCPCB_APP_ID, TAXJLCPCB_ACCESS_KEY and TAXJLCPCB_SECRET_KEY in wp-config.php.', 'taxapijlcpcb') . '</p>';

        if ($this->source->secretBroken()) {
            $html .= '<div class="notice notice-warning inline"><p>' . esc_html__('The stored SecretKey no longer opens — the salts in wp-config.php changed. Enter it again.', 'taxapijlcpcb') . '</p></div>';
        }

        return $html . '<table class="form-table" role="presentation"><tbody>'
            . $this->inputRow('app_id', __('App ID', 'taxapijlcpcb'), 'text', $shown['app_id'], '')
            . $this->inputRow('access_key', __('AccessKey', 'taxapijlcpcb'), 'text', $shown['access_key'], '')
            . $this->inputRow('secret_key', __('SecretKey', 'taxapijlcpcb'), 'password', '', $shown['has_secret'] ? __('stored — leave empty to keep it', 'taxapijlcpcb') : '')
            . '</tbody></table>';
    }

    private function inputRow(string $name, string $label, string $type, string $value, string $placeholder): string
    {
        return '<tr><th scope="row"><label for="taxapijlcpcb-' . esc_attr($name) . '">' . esc_html($label) . '</label></th>'
            . '<td><input type="' . esc_attr($type) . '" class="regular-text" autocomplete="off" id="taxapijlcpcb-' . esc_attr($name)
            . '" name="taxapijlcpcb_' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '"></td></tr>';
    }
}

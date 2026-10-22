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
        // The version is drawn by the modeller under the tab bar (D-968).
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

        $html .= $this->orders($credentials !== null);

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

    /** Step 2: the probe list of order numbers, read-only. */
    private function orders(bool $ready): string
    {
        $html = '<h2>' . esc_html__('3 · Orders (probe, nothing is written)', 'taxapijlcpcb') . '</h2>';

        if ($ready) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=taxapijlcpcb_list'), 'taxapijlcpcb_list');
            $html .= '<p><a class="button" href="' . esc_url($url) . '">' . esc_html(sprintf(
                /* translators: %d: days */
                __('List orders of the last %d days', 'taxapijlcpcb'),
                Plugin::LIST_DAYS
            )) . '</a></p>';
        }

        $last = get_option('taxmod_jlcpcb_last_list');

        if (! is_array($last) || ! is_array($last['batches'] ?? null)) {
            return $html;
        }

        $html .= '<p>' . esc_html(sprintf(
            /* translators: %s: date */
            __('Listed %s:', 'taxapijlcpcb'),
            (string) ($last['at'] ?? '')
        )) . '</p><table class="widefat striped" style="max-width:40em"><thead><tr><th>'
            . esc_html__('Order number', 'taxapijlcpcb') . '</th><th>' . esc_html__('Orders by type', 'taxapijlcpcb') . '</th><th></th></tr></thead><tbody>';

        foreach ($last['batches'] as $number => $types) {
            $parts = [];

            foreach (is_array($types) ? $types : [] as $type => $count) {
                $parts[] = (int) $count . ' × ' . (string) $type;
            }

            $action = 'taxapijlcpcb_detail_' . (string) $number;
            $url = wp_nonce_url(admin_url('admin-post.php?action=taxapijlcpcb_detail&batch=' . rawurlencode((string) $number)), $action);
            $html .= '<tr><td><code>' . esc_html((string) $number) . '</code></td><td>' . esc_html(implode(', ', $parts))
                . '</td><td><a href="' . esc_url($url) . '">' . esc_html__('Details', 'taxapijlcpcb') . '</a></td></tr>';
        }

        return $html . '</tbody></table>' . $this->detail() . $this->enrichError();
    }

    /** The last probed batch; the shipping address was never read. */
    private function detail(): string
    {
        $last = get_option('taxmod_jlcpcb_last_detail');

        if (! is_array($last) || ! is_array($last['items'] ?? null)) {
            return '';
        }

        $money = static fn (mixed $v): string => is_numeric($v) ? number_format((float) $v, 2, '.', '') . ' USD' : '–';
        $html = '<h3>' . esc_html(sprintf(
            /* translators: 1: order number, 2: date */
            __('Details of %1$s (read %2$s)', 'taxapijlcpcb'),
            (string) ($last['batch'] ?? ''),
            (string) ($last['at'] ?? '')
        )) . '</h3><p>' . esc_html(sprintf(
            /* translators: 1: shipping fee, 2: shipping method, 3: customs, 4: total, 5: tracking number */
            __('Shipping %1$s (%2$s), customs %3$s, total %4$s, tracking %5$s', 'taxapijlcpcb'),
            $money($last['carriage'] ?? null),
            (string) ($last['shipping'] ?? '?'),
            $money($last['customs'] ?? null),
            $money($last['total'] ?? null),
            (string) ($last['tracking'] ?? '–')
        )) . (! empty($last['stencils']) ? ' ' . esc_html(sprintf(
            /* translators: %d: number of stencil orders */
            __('Plus %d stencil orders (not read yet).', 'taxapijlcpcb'),
            (int) $last['stencils']
        )) : '') . '</p><table class="widefat striped" style="max-width:60em"><thead><tr><th>' . esc_html__('File', 'taxapijlcpcb')
            . '</th><th>' . esc_html__('Quantity', 'taxapijlcpcb') . '</th><th>' . esc_html__('Price', 'taxapijlcpcb')
            . '</th><th>' . esc_html__('Status', 'taxapijlcpcb') . '</th></tr></thead><tbody>';

        foreach ($last['items'] as $item) {
            $item = is_array($item) ? $item : [];
            $html .= '<tr><td>' . esc_html((string) ($item['file'] ?? '')) . '</td><td>' . esc_html((string) ($item['count'] ?? ''))
                . '</td><td>' . esc_html($money($item['price'] ?? null)) . '</td><td>' . esc_html(self::status($item['status'] ?? null)) . '</td></tr>';
        }

        $html .= '</tbody></table>';
        $fields = is_array($last['money'] ?? null) ? $last['money'] : [];

        if ($fields !== []) {
            $html .= '<details><summary>' . esc_html__('All fields JLC sent (without address)', 'taxapijlcpcb') . '</summary><table class="widefat striped" style="max-width:60em"><tbody>';

            foreach ($fields as $path => $value) {
                $html .= '<tr><td><code>' . esc_html((string) $path) . '</code></td><td>' . esc_html((string) $value) . '</td></tr>';
            }

            $html .= '</tbody></table></details>';
        }

        return $html;
    }

    private function enrichError(): string
    {
        $error = get_option(JlcpcbSource::ERROR_OPTION);

        return is_array($error) ? '<p class="description">' . esc_html(sprintf(
            /* translators: 1: date, 2: order number, 3: error */
            __('Last failed price completion %1$s for %2$s: %3$s', 'taxapijlcpcb'),
            (string) ($error['at'] ?? ''),
            (string) ($error['order'] ?? ''),
            (string) ($error['message'] ?? '')
        )) . '</p>' : '';
    }

    /** Codes of JLC's first version of the call; V2 names none, so an unknown code shows as its number. */
    private static function status(mixed $code): string
    {
        return match (is_numeric($code) ? (int) $code : -1) {
            0 => __('cancelled', 'taxapijlcpcb'),
            1 => __('pending review', 'taxapijlcpcb'),
            2 => __('awaiting confirmation', 'taxapijlcpcb'),
            3 => __('confirmed', 'taxapijlcpcb'),
            4 => __('in production', 'taxapijlcpcb'),
            5 => __('shipped', 'taxapijlcpcb'),
            default => is_numeric($code) ? (string) $code : '',
        };
    }

    /** The steps of the «How to connect» box the modeller draws beside the tab (D-963), escaped. */
    public static function guide(): string
    {
        $steps = [
            __('Open the JLC API console (api.jlcpcb.com) with your JLCPCB account. The app must exist and its service must be started.', 'taxapijlcpcb'),
            __('Create an API key for the app. You get three values: App ID, AccessKey and SecretKey. Never post them in a chat.', 'taxapijlcpcb'),
            __('Enter the three values in this tab and save. The SecretKey is stored encrypted and is not shown again. Constants in wp-config.php (TAXJLCPCB_APP_ID, TAXJLCPCB_ACCESS_KEY, TAXJLCPCB_SECRET_KEY) take precedence.', 'taxapijlcpcb'),
            __('Click «Test connection». It reads one public component and changes nothing.', 'taxapijlcpcb'),
            __('Click «List orders of the last 365 days» to see the order numbers of the last year, and «Details» for the amounts of one order.', 'taxapijlcpcb'),
            __('From then on the module brings your JLCPCB orders into the inbox four times a day; the first run reaches back a year and compares them with the model. Mail only adds the status. Amounts stay in USD.', 'taxapijlcpcb'),
        ];

        return '<ol>' . implode('', array_map(static fn (string $step): string => '<li>' . esc_html($step) . '</li>', $steps)) . '</ol>'
            . '<p>' . esc_html__('The IP whitelist of the app may stay empty. If you fill it, add the outgoing IP of the web server.', 'taxapijlcpcb') . '</p>';
    }
}

<?php declare(strict_types=1);

/**
 * Plugin Name:       Taxmod JLCPCB
 * Description:       Connects the owner's JLCPCB API app and completes JLCPCB orders for the Taxonomy Modeller with prices and production state; only picked data is written.
 * Version:           0.0.4
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Stefan Fambach (SteFam)
 * Author URI:        https://www.fambach.net
 * License:           PolyForm Noncommercial 1.0.0
 * License URI:       https://polyformproject.org/licenses/noncommercial/1.0.0/
 * Text Domain:       taxapijlcpcb
 * GitHub Plugin URI: sfambach/wp_tax_api_jlcpcb
 * Primary Branch:    main
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */

if (! defined('ABSPATH')) {
    exit;
}

$taxapijlcpcbAutoloader = __DIR__ . '/vendor/autoload.php';

if (! is_readable($taxapijlcpcbAutoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Taxmod JLCPCB:</strong> '
            . esc_html__('run "composer install" in the plugin folder — the autoloader is missing.', 'taxapijlcpcb')
            . '</p></div>';
    });

    return;
}

require_once $taxapijlcpcbAutoloader;

TaxApiJlcpcb\WordPress\Plugin::boot();

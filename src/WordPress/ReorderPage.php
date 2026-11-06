<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use TaxApiJlcpcb\Core\Reorder\BoardSpecs;
use TaxApiJlcpcb\Core\Reorder\RevisionFields;

/**
 * «Reorder at JLCPCB» on a board revision of the modeller (D-998): a button in the record view and a page with the
 * values to type into JLC's quote form. Reads only, through the modeller's abilities; ordering and paying stay by hand
 * (his word 2026-10-28: «dein 2. vorschalg hört sich gut an» — no ordering over the API).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/jlc/konzept.md
 */
final class ReorderPage
{
    public const PAGE      = 'taxapijlcpcb-reorder';
    public const QUOTE_URL = 'https://cart.jlcpcb.com/quote';

    public static function boot(): void
    {
        // The filter of the modeller 0.1.74 or later; an older one simply never asks.
        add_filter('taxmod_record_actions', [self::class, 'action'], 10, 3);
        add_action('admin_menu', [self::class, 'register'], 20);
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    public static function action(array $actions, int $nodeId, int $recordId): array
    {
        if ($nodeId !== RevisionFields::NODE || ! current_user_can(Plugin::CAPABILITY)) {
            return $actions;
        }

        $actions[] = [
            'label' => __('Reorder at JLCPCB', 'taxapijlcpcb'),
            'url'   => admin_url('admin.php?page=' . self::PAGE . '&record=' . $recordId),
            'title' => __('Shows the board settings to type into the JLCPCB quote form', 'taxapijlcpcb'),
        ];

        return $actions;
    }

    /** A page without a menu entry: it is reached from the button only. */
    public static function register(): void
    {
        add_submenu_page('taxmod', __('Reorder at JLCPCB', 'taxapijlcpcb'), '', Plugin::CAPABILITY, self::PAGE, static function (): void {
            print self::render(isset($_GET['record']) ? absint(wp_unslash((string) $_GET['record'])) : 0); // phpcs:ignore WordPress.Security -- read-only page, escaped in render()
        });
        remove_submenu_page('taxmod', self::PAGE);
    }

    private static function render(int $recordId): string
    {
        if (! current_user_can(Plugin::CAPABILITY)) {
            return '';
        }

        $html = '<div class="wrap">' . (class_exists(\Taxmod\WordPress\Admin\ListPage::class) ? \Taxmod\WordPress\Admin\ListPage::heading(__('Reorder at JLCPCB', 'taxapijlcpcb'), Plugin::VERSION) : '');

        try {
            $specs = self::specs($recordId);
        } catch (\RuntimeException $failure) {
            return $html . '<div class="notice notice-error inline"><p>' . esc_html($failure->getMessage()) . '</p></div></div>';
        }

        $rows = [
            __('Layers', 'taxapijlcpcb')            => $specs->layers === null ? null : (string) $specs->layers,
            __('PCB thickness (mm)', 'taxapijlcpcb') => $specs->thicknessMm,
            __('Solder mask colour', 'taxapijlcpcb') => $specs->solderMaskColour,
            __('Surface finish', 'taxapijlcpcb')     => $specs->surfaceFinish,
            __('Outer copper (oz)', 'taxapijlcpcb')  => $specs->outerCopperOz,
            __('Inner copper (oz)', 'taxapijlcpcb')  => $specs->innerCopperOz,
        ];

        $html .= '<h2>' . esc_html($specs->name) . '</h2>';

        if ($specs->isEmpty()) {
            $html .= '<div class="notice notice-warning inline"><p>' . esc_html__('This revision holds no manufacturing settings yet. Fill them in the record first.', 'taxapijlcpcb') . '</p></div>';
        }

        $html .= '<table class="widefat striped" style="max-width:40em"><tbody>';

        foreach ($rows as $label => $value) {
            $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . ($value === null ? '<span class="description">—</span>' : '<code>' . esc_html($value) . '</code>') . '</td></tr>';
        }

        return $html . '</tbody></table>'
            . '<p>' . esc_html__('Upload the Gerber file in the quote form, type in these values, then order and pay at JLCPCB as usual. Nothing is ordered from here.', 'taxapijlcpcb') . '</p>'
            . '<p><a class="button button-primary" href="' . esc_url(self::QUOTE_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open the JLCPCB quote form', 'taxapijlcpcb') . '</a> '
            . '<a class="button" href="' . esc_url(add_query_arg(['page' => 'taxmod', 'taxmod_open_record' => (string) $recordId], admin_url('admin.php'))) . '">' . esc_html__('Back to the record', 'taxapijlcpcb') . '</a></p></div>';
    }

    private static function specs(int $recordId): BoardSpecs
    {
        if (! class_exists(\Taxmod\WordPress\ModelDoor::class)) {
            throw new \RuntimeException(__('The Taxonomy Modeller does not answer — is it active?', 'taxapijlcpcb'));
        }

        $door   = new \Taxmod\WordPress\ModelDoor();
        $values = self::read(static fn (): array => $door->records(RevisionFields::NODE))[$recordId] ?? null;

        if ($values === null) {
            throw new \RuntimeException(__('This record is not a board revision.', 'taxapijlcpcb'));
        }

        $labels = [];

        foreach (RevisionFields::CHOICE_NODES as $node) {
            foreach (self::read(static fn (): array => $door->records($node)) as $id => $fields) {
                if (isset($fields[RevisionFields::NAME][0])) {
                    $labels[$id] = $fields[RevisionFields::NAME][0];
                }
            }
        }

        return BoardSpecs::fromValues($values, $labels);
    }

    /**
     * Through the modeller's one door (D-1186); a refusal is worded for this page.
     *
     * @param callable(): array<int, array<int, list<string>>> $read
     * @return array<int, array<int, list<string>>>
     */
    private static function read(callable $read): array
    {
        try {
            return $read();
        } catch (\Taxmod\Core\Exception\ModelDoorRefused $refused) {
            throw new \RuntimeException($refused->getMessage());
        }
    }
}

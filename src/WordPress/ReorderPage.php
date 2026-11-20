<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

use Taxmod\WordPress\Admin\ListPage;
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
        // D-1394: the roles this page needs, so the modeller can say which are not set on this site.
        add_filter('taxmod_roles', static function (array $roles): array {
            foreach (RevisionFields::ROLES as $group => $keys) {
                foreach ($keys as $key => $required) {
                    $roles[] = ['module' => 'jlcpcb', 'group' => $group, 'key' => $key, 'required' => $required];
                }
            }

            return $roles;
        });
    }

    /** The revision's fields by role (D-1394); null while the modeller has no role table or a role is not set here. */
    private static function fields(): ?RevisionFields
    {
        if (! method_exists(\Taxmod\WordPress\ModelDoor::class, 'roles')) {
            return null;
        }

        try {
            $roles = \Taxmod\WordPress\ModelDoor::roles();

            return RevisionFields::fromRoles($roles->node(...), $roles->field(...));
        } catch (\Taxmod\Core\Exception\RoleMissing) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    public static function action(array $actions, int $nodeId, int $recordId): array
    {
        if (! current_user_can(Plugin::CAPABILITY) || $nodeId !== self::fields()?->node) {
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

        $back = ListPage::back(add_query_arg(['page' => 'taxmod', 'taxmod_open_record' => (string) $recordId], admin_url('admin.php')), __('Back to the record', 'taxapijlcpcb'));

        try {
            $specs = self::specs($recordId);
        } catch (\RuntimeException $failure) {
            return self::framed($back . '<div class="notice notice-error inline"><p>' . esc_html($failure->getMessage()) . '</p></div>');
        }

        $rows = [
            __('Layers', 'taxapijlcpcb')            => $specs->layers === null ? null : (string) $specs->layers,
            __('PCB thickness (mm)', 'taxapijlcpcb') => $specs->thicknessMm,
            __('Solder mask colour', 'taxapijlcpcb') => $specs->solderMaskColour,
            __('Surface finish', 'taxapijlcpcb')     => $specs->surfaceFinish,
            __('Outer copper (oz)', 'taxapijlcpcb')  => $specs->outerCopperOz,
            __('Inner copper (oz)', 'taxapijlcpcb')  => $specs->innerCopperOz,
        ];

        $html = $back . '<h2>' . esc_html($specs->name) . '</h2>';

        if ($specs->isEmpty()) {
            $html .= '<div class="notice notice-warning inline"><p>' . esc_html__('This revision holds no manufacturing settings yet. Fill them in the record first.', 'taxapijlcpcb') . '</p></div>';
        }

        $facts = [];

        foreach ($rows as $label => $value) {
            $facts[] = [esc_html($label), $value === null ? '<span class="description">—</span>' : '<code>' . esc_html($value) . '</code>'];
        }

        // D-1308: the act in the toolbar above, D-1409: the facts in the shared read-only table.
        return self::framed($html
            . ListPage::toolbar([ListPage::button(__('Open the JLCPCB quote form', 'taxapijlcpcb'), self::QUOTE_URL, true, attributes: ['target' => '_blank', 'rel' => 'noopener noreferrer'])])
            . ListPage::grid([], $facts)
            . '<p>' . esc_html__('Upload the Gerber file in the quote form, type in these values, then order and pay at JLCPCB as usual. Nothing is ordered from here.', 'taxapijlcpcb') . '</p>');
    }

    /** D-1373, G16: the page in the shared template. */
    private static function framed(string $body): string
    {
        return ListPage::page(__('Reorder at JLCPCB', 'taxapijlcpcb'), [], [['html' => $body]], Plugin::VERSION);
    }

    private static function specs(int $recordId): BoardSpecs
    {
        if (! class_exists(\Taxmod\WordPress\ModelDoor::class)) {
            throw new \RuntimeException(__('The Taxonomy Modeller does not answer — is it active?', 'taxapijlcpcb'));
        }

        $fields = self::fields() ?? throw new \RuntimeException(__('The Taxonomy Modeller does not know where board revisions are kept — update it or set the roles.', 'taxapijlcpcb'));
        $door   = new \Taxmod\WordPress\ModelDoor();
        $values = self::read(static fn (): array => $door->records($fields->node))[$recordId] ?? null;

        if ($values === null) {
            throw new \RuntimeException(__('This record is not a board revision.', 'taxapijlcpcb'));
        }

        $labels = [];

        foreach ($fields->choiceNodes as $node) {
            foreach (self::read(static fn (): array => $door->records($node)) as $id => $choice) {
                if (isset($choice[$fields->name][0])) {
                    $labels[$id] = $choice[$fields->name][0];
                }
            }
        }

        return BoardSpecs::fromValues($values, $labels, $fields);
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

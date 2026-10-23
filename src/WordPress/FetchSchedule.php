<?php declare(strict_types=1);

namespace TaxApiJlcpcb\WordPress;

/**
 * How often the modeller's inbox fetches JLC orders, chosen in the tab like at eBay — JLC far less often
 * (his word 2026-10-23: «jlc brauchen wir nicht so oft wie ebay»).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md
 */
final class FetchSchedule
{
    public const OPTION  = 'taxmod_jlcpcb_schedule';
    public const DEFAULT = 'daily';

    /** Zero means the inbox only fetches on its button. */
    public const INTERVALS = ['' => 0, 'hourly' => 3600, 'fourtimesdaily' => 21600, 'twicedaily' => 43200, 'daily' => 86400];

    public static function current(): string
    {
        $stored = get_option(self::OPTION, self::DEFAULT);

        return is_string($stored) && array_key_exists($stored, self::INTERVALS) ? $stored : self::DEFAULT;
    }

    public static function seconds(): int
    {
        return self::INTERVALS[self::current()];
    }

    public static function save(string $schedule): void
    {
        update_option(self::OPTION, array_key_exists($schedule, self::INTERVALS) ? $schedule : self::DEFAULT, false);
    }

    /** @return array<string, string> value => label */
    public static function choices(): array
    {
        return [
            ''               => __('never (Inbox button only)', 'taxapijlcpcb'),
            'hourly'         => __('every hour', 'taxapijlcpcb'),
            'fourtimesdaily' => __('four times a day', 'taxapijlcpcb'),
            'twicedaily'     => __('twice a day', 'taxapijlcpcb'),
            'daily'          => __('once a day', 'taxapijlcpcb'),
        ];
    }
}

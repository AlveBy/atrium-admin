<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Dashboard\PageSpeedStats;

/**
 * "Site speed" — median page generation time.
 *
 * The scale is the same as in the Bitrix gadget (bitrix/gadgets/bitrix/admin_sitespeed):
 * a 0–2.5 s ruler, five bands labelled "Very fast … Very slow" with the same
 * thresholds. The number comes from elsewhere: Bitrix posted the licence key to
 * its vendor's server and drew p50 from their analytics (that request often
 * returned nothing, leaving the gadget at "no data"); we take the median of our
 * own measurements.
 */
final class SiteSpeedGadget extends Gadget
{
    /** Top of the ruler, ms. From the gadget: siteIndexPercent = p50 / 2500 * 100. */
    private const SCALE_MS = 2500;

    /** Band thresholds, ms => label key. From intervals[] in the gadget's index.php. */
    private const BANDS = [
        500 => 'very_fast',
        1000 => 'fast',
        1500 => 'not_fast',
        2000 => 'slow',
        2500 => 'very_slow',
    ];

    public function __construct(array $options = [], private ?PageSpeedStats $stats = null)
    {
        parent::__construct($options);

        $this->stats ??= new PageSpeedStats((int) $this->option('window_days', 7));
    }

    public function component(): string
    {
        return 'SiteSpeedGadget';
    }

    public function title(): string
    {
        return (string) $this->option('title', __('atrium::dashboard.site_speed.title'));
    }

    public function width(): string
    {
        return $this->option('width', 'half') === 'full' ? 'full' : 'half';
    }

    public function data(): array
    {
        $median = $this->stats->medianMs();

        return [
            'seconds' => $median === null ? null : number_format($median / 1000, 2, ',', ' '),
            'percent' => $median === null ? null : $this->pointerPercent($median),
            'label' => $median === null ? __('atrium::dashboard.site_speed.no_data') : $this->band($median),
            'unit' => __('atrium::dashboard.site_speed.unit'),
            'samples' => $this->stats->samples(),
            'windowDays' => $this->stats->windowDays(),
            'bands' => array_map(self::bandLabel(...), array_values(self::BANDS)),
            'scaleSeconds' => number_format(self::SCALE_MS / 1000, 1, ',', ' '),
        ];
    }

    /** Pointer position, %. Bitrix clamped it to 4…98 so it does not slide off the edges. */
    private function pointerPercent(int $ms): float
    {
        return round(min(max($ms / self::SCALE_MS * 100, 4), 98), 2);
    }

    private function band(int $ms): string
    {
        foreach (self::BANDS as $threshold => $band) {
            if ($ms < $threshold) {
                return self::bandLabel($band);
            }
        }

        return self::bandLabel(array_values(self::BANDS)[count(self::BANDS) - 1]);
    }

    private static function bandLabel(string $band): string
    {
        return __('atrium::dashboard.site_speed.bands.'.$band);
    }
}

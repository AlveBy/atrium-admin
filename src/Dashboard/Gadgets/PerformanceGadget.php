<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Dashboard\PageSpeedStats;

/**
 * "Performance monitor" — the current score in pages per second.
 *
 * In Bitrix the number came from the perfmon.mark_php_page_rate option, which
 * the module wrote with the formula `samples / total time` (perfmon_panel.php)
 * after a manual test run — i.e. pages per second — and it stayed whatever the
 * last run left, even a year ago.
 *
 * Same formula and unit here, but computed from live requests, so the number
 * is not directly comparable with Bitrix: there it was a synthetic test of one
 * page, here it is all real traffic including the heaviest pages.
 */
final class PerformanceGadget extends Gadget
{
    public function __construct(array $options = [], private ?PageSpeedStats $stats = null)
    {
        parent::__construct($options);

        $this->stats ??= new PageSpeedStats((int) $this->option('window_days', 7));
    }

    public function component(): string
    {
        return 'PerformanceGadget';
    }

    public function title(): string
    {
        return (string) $this->option('title', __('atrium::dashboard.performance.title'));
    }

    public function width(): string
    {
        return $this->option('width', 'half') === 'full' ? 'full' : 'half';
    }

    public function data(): array
    {
        $rate = $this->stats->pagesPerSecond();
        $average = $this->stats->averageMs();

        return [
            'rate' => $rate === null ? null : number_format($rate, 2, ',', ' '),
            'caption' => __('atrium::dashboard.performance.caption'),
            'unit' => __('atrium::dashboard.performance.unit'),
            'averageSeconds' => $average === null ? null : number_format($average / 1000, 3, ',', ' '),
            'samples' => $this->stats->samples(),
            'windowDays' => $this->stats->windowDays(),
            'slowest' => $this->stats->slowest(),
        ];
    }
}

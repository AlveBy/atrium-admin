<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Dashboard\PageSpeedStats;

/**
 * «Монитор производительности» — текущая оценка в страницах в секунду.
 *
 * У заказчика в этой плитке стояло 32,23. Число приходило из опции
 * perfmon.mark_php_page_rate, которую модуль записывал по формуле
 * `количество замеров / сумма времени` (perfmon_panel.php) после ручного
 * прогона теста, — то есть страниц в секунду, и оно оставалось таким, каким
 * его оставил последний прогон, хоть год назад.
 *
 * Формулу и единицу берём ту же, но считаем по живым запросам, поэтому число
 * не сравнивается с битриксовым напрямую: там синтетика на одной странице,
 * здесь весь реальный трафик вместе с самыми тяжёлыми страницами.
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
        return (string) $this->option('title', 'Монитор производительности');
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
            'caption' => 'Текущая оценка',
            'unit' => 'страниц в секунду',
            'averageSeconds' => $average === null ? null : number_format($average / 1000, 3, ',', ' '),
            'samples' => $this->stats->samples(),
            'windowDays' => $this->stats->windowDays(),
            'slowest' => $this->stats->slowest(),
        ];
    }
}

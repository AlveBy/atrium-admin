<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Dashboard\PageSpeedStats;

/**
 * «Скорость сайта» — медиана времени генерации страницы.
 *
 * Шкала один в один из гаджета Битрикса (bitrix/gadgets/bitrix/admin_sitespeed):
 * линейка 0–2.5 сек, пять полос и подписи «Очень быстро … Очень медленно» с
 * теми же порогами. Источник числа другой: Битрикс постил лицензионный ключ на
 * www.1c-bitrix.ru и рисовал p50 из их аналитики (у заказчика этот запрос
 * ничего не возвращал — гаджет так и висел с «нет данных»), а мы берём медиану
 * собственных замеров.
 */
final class SiteSpeedGadget extends Gadget
{
    /** Верх линейки, мс. Из gadget: siteIndexPercent = p50 / 2500 * 100. */
    private const SCALE_MS = 2500;

    /** Пороги полос, мс => подпись. Из intervals[] в index.php гаджета. */
    private const BANDS = [
        500 => 'Очень быстро',
        1000 => 'Быстро',
        1500 => 'Не быстро',
        2000 => 'Медленно',
        2500 => 'Очень медленно',
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
        return (string) $this->option('title', 'Скорость сайта');
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
            'label' => $median === null ? 'нет данных' : $this->band($median),
            'unit' => 'Сек.',
            'samples' => $this->stats->samples(),
            'windowDays' => $this->stats->windowDays(),
            'bands' => array_values(self::BANDS),
            'scaleSeconds' => number_format(self::SCALE_MS / 1000, 1, ',', ' '),
        ];
    }

    /** Положение указателя, %. Битрикс зажимал его в 4…98, чтобы не съезжал за края. */
    private function pointerPercent(int $ms): float
    {
        return round(min(max($ms / self::SCALE_MS * 100, 4), 98), 2);
    }

    private function band(int $ms): string
    {
        foreach (self::BANDS as $threshold => $label) {
            if ($ms < $threshold) {
                return $label;
            }
        }

        return (string) array_values(self::BANDS)[count(self::BANDS) - 1];
    }
}

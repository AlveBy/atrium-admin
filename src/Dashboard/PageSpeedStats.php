<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard;

use Atrium\Admin\Models\PageTiming;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Summary of page generation timings — what the "Site speed" and
 * "Performance monitor" gadgets live on.
 *
 * Computed once for all gadgets and cached: the dashboard is opened often,
 * while the window is a week, so there is no point recomputing it on every visit.
 */
final class PageSpeedStats
{
    /** @var array{samples:int,median_ms:?int,average_ms:?float,rate:?float,slowest:array<int,array{path:string,ms:int,hits:int}>}|null */
    private ?array $data = null;

    public function __construct(
        private readonly int $windowDays = 7,
        private readonly int $ttlSeconds = 300,
    ) {}

    /** Number of measurements in the window. */
    public function samples(): int
    {
        return $this->data()['samples'];
    }

    /** Median generation time, ms. */
    public function medianMs(): ?int
    {
        return $this->data()['median_ms'];
    }

    /** Average generation time, ms. */
    public function averageMs(): ?float
    {
        return $this->data()['average_ms'];
    }

    /**
     * Pages per second.
     *
     * The Bitrix formula from perfmon_panel.php: number of measurements divided
     * by the total time. Not a rough 1/average, but exactly the number Bitrix
     * stored in mark_php_page_rate and showed as "Current score".
     */
    public function pagesPerSecond(): ?float
    {
        return $this->data()['rate'];
    }

    /** @return array<int,array{path:string,ms:int,hits:int}> */
    public function slowest(): array
    {
        return $this->data()['slowest'];
    }

    public function windowDays(): int
    {
        return $this->windowDays;
    }

    /**
     * @return array{samples:int,median_ms:?int,average_ms:?float,rate:?float,slowest:array<int,array{path:string,ms:int,hits:int}>}
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $empty = ['samples' => 0, 'median_ms' => null, 'average_ms' => null, 'rate' => null, 'slowest' => []];

        try {
            $this->data = Cache::remember(
                'atrium.page-speed.'.$this->windowDays,
                $this->ttlSeconds,
                fn () => $this->compute()
            );
        } catch (Throwable) {
            // No table (the migration has not run yet) or the cache is down —
            // the dashboard must still open, just without numbers.
            $this->data = $empty;
        }

        return $this->data ?? $empty;
    }

    /**
     * @return array{samples:int,median_ms:?int,average_ms:?float,rate:?float,slowest:array<int,array{path:string,ms:int,hits:int}>}
     */
    private function compute(): array
    {
        $since = now()->subDays($this->windowDays);

        $totals = PageTiming::where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) AS samples, SUM(duration_ms) AS total_ms')
            ->first();

        $samples = (int) ($totals->samples ?? 0);

        if ($samples === 0) {
            return ['samples' => 0, 'median_ms' => null, 'average_ms' => null, 'rate' => null, 'slowest' => []];
        }

        $totalMs = (float) ($totals->total_ms ?? 0);

        $median = PageTiming::where('created_at', '>=', $since)
            ->orderBy('duration_ms')
            ->skip(intdiv($samples, 2))
            ->take(1)
            ->value('duration_ms');

        $slowest = PageTiming::where('created_at', '>=', $since)
            ->selectRaw('path, AVG(duration_ms) AS avg_ms, COUNT(*) AS hits')
            ->groupBy('path')
            ->orderByDesc('avg_ms')
            ->take(5)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'path' => (string) $row->path,
                'ms' => (int) round((float) $row->avg_ms),
                'hits' => (int) $row->hits,
            ])
            ->all();

        return [
            'samples' => $samples,
            'median_ms' => $median === null ? null : (int) $median,
            'average_ms' => $totalMs / $samples,
            'rate' => $totalMs > 0 ? $samples / ($totalMs / 1000) : null,
            'slowest' => $slowest,
        ];
    }
}

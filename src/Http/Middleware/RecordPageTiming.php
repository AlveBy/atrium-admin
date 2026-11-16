<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\PageTiming;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Пишет время генерации страниц публичной части.
 *
 * Запись идёт в terminate(), то есть уже после того, как ответ ушёл клиенту, —
 * посетитель за замер не платит.
 *
 * Считаем только то, что видит посетитель: GET, успешный ответ, HTML, не
 * админка и не XHR. Иначе в медиану попадут ajax-обработчики форм и статика, и
 * «скорость сайта» перестанет означать скорость сайта.
 */
class RecordPageTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('atrium.dashboard.timings.enabled', true)) {
            return;
        }

        if (! $this->shouldRecord($request, $response)) {
            return;
        }

        $rate = (float) config('atrium.dashboard.timings.sample_rate', 1.0);

        if ($rate < 1.0 && mt_rand() / mt_getrandmax() > $rate) {
            return;
        }

        // Замер не стоит того, чтобы из-за него падала страница: упавшая
        // вставка (нет таблицы, недоступна база) должна остаться незаметной.
        try {
            PageTiming::create([
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'duration_ms' => $this->durationMs($request),
            ]);

            $this->prune();
        } catch (Throwable) {
            // Молча: ответ клиенту уже отдан, логировать тут нечего и некуда.
        }
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->hasHeader('X-Inertia')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        $adminPrefix = trim((string) config('atrium.prefix', 'admin'), '/');

        return $adminPrefix === '' || ! $request->is($adminPrefix, $adminPrefix.'/*');
    }

    private function durationMs(Request $request): int
    {
        $start = defined('LARAVEL_START')
            ? LARAVEL_START
            : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        $ms = (int) round((microtime(true) - $start) * 1000);

        // Верхняя граница — чтобы одиночный таймаут не увёл шкалу в бесконечность.
        return max(0, min($ms, 600000));
    }

    /**
     * Удаляет замеры старше окна отчёта.
     *
     * Раз в сотню записей, чтобы не гонять DELETE на каждый хит и не заводить
     * ради этого крон: гаджет должен работать на голой установке.
     */
    private function prune(): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $days = max(1, (int) config('atrium.dashboard.timings.keep_days', 30));

        PageTiming::where('created_at', '<', now()->subDays($days))->delete();
    }
}

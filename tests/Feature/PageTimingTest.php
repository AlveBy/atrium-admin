<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\RecordPageTiming;
use Atrium\Admin\Models\PageTiming;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Generation time of public pages, measured for the speed gadgets.
 */
final class PageTimingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.dashboard.timings.enabled', true);
        $app['config']->set('atrium.dashboard.timings.sample_rate', 1.0);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/', fn () => '<h1>Home</h1>');
            Route::get('/news', fn () => '<h1>News</h1>');
            Route::post('/news', fn () => '<h1>Sent</h1>');
            Route::get('/feed.json', fn () => ['items' => []]);
            Route::get('/moved', fn () => redirect('/news'));
            Route::get('/missing', fn () => abort(404));
            Route::get('/administrator', fn () => '<h1>Not the panel</h1>');
            Route::get('{path}', fn () => '<h1>Deep</h1>')->where('path', '.*');
        });
    }

    public function test_a_public_page_is_measured_after_it_is_sent(): void
    {
        $this->get('/news')->assertOk();
        $this->get('/administrator')->assertOk();

        $this->assertSame(['/news', '/administrator'], PageTiming::query()->orderBy('id')->pluck('path')->all());
        $timing = PageTiming::query()->firstOrFail();
        $this->assertGreaterThanOrEqual(0, $timing->duration_ms);
        $this->assertLessThan(600000, $timing->duration_ms);
        $this->assertNotNull($timing->created_at);
    }

    /**
     * @return array<string,array{0:string,1:string,2:array<string,string>}>
     */
    public static function notAVisitorPage(): array
    {
        return [
            'a form post' => ['POST', '/news', []],
            'an ajax call' => ['GET', '/news', ['X-Requested-With' => 'XMLHttpRequest']],
            'an Inertia visit' => ['GET', '/news', ['X-Inertia' => 'true']],
            'JSON' => ['GET', '/feed.json', []],
            'a redirect' => ['GET', '/moved', []],
            'a missing page' => ['GET', '/missing', []],
            'the panel login' => ['GET', '/admin/login', []],
        ];
    }

    /**
     * @param  array<string,string>  $headers
     */
    #[DataProvider('notAVisitorPage')]
    public function test_what_a_visitor_does_not_see_is_not_measured(string $method, string $uri, array $headers): void
    {
        $this->call($method, $uri, server: $this->transformHeadersToServerVars($headers));

        $this->assertSame(0, PageTiming::query()->count());
    }

    public function test_the_panel_dashboard_is_not_measured(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk();

        $this->assertSame(0, PageTiming::query()->count());
    }

    public function test_with_the_panel_at_the_root_every_page_is_measured(): void
    {
        config(['atrium.prefix' => '/']);

        $this->get('/')->assertOk();
        $this->get('/deep/page')->assertOk();

        $this->assertSame(['/', '/deep/page'], PageTiming::query()->orderBy('id')->pluck('path')->all());
    }

    public function test_switching_timings_off_stops_the_measurement(): void
    {
        config(['atrium.dashboard.timings.enabled' => false]);

        $this->get('/news')->assertOk();

        $this->assertSame(0, PageTiming::query()->count());
    }

    public function test_a_long_address_is_cut_to_the_column(): void
    {
        $this->get('/'.str_repeat('a', 300))->assertOk();

        $this->assertSame('/'.str_repeat('a', 254), PageTiming::query()->value('path'));
    }

    public function test_a_long_address_is_cut_by_characters_not_bytes(): void
    {
        // A server may hand over the address as raw UTF-8 bytes.
        $request = new Request(server: ['REQUEST_URI' => '/'.str_repeat('я', 300), 'REQUEST_METHOD' => 'GET', 'REQUEST_TIME_FLOAT' => microtime(true)]);
        (new RecordPageTiming)->terminate($request, new Response('<h1>News</h1>', 200, ['Content-Type' => 'text/html']));

        $this->assertSame('/'.str_repeat('я', 254), PageTiming::query()->value('path'));
    }

    public function test_a_failed_write_goes_unnoticed(): void
    {
        $default = config('database.default');
        config([
            'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/atrium/timings.sqlite', 'prefix' => ''],
            'database.default' => 'broken',
        ]);

        try {
            $this->terminate(microtime(true));
        } finally {
            config(['database.default' => $default]);
        }

        $this->assertSame(0, PageTiming::query()->count());
    }

    public function test_handle_passes_the_response_through_untouched(): void
    {
        $response = new Response('<p>x</p>');

        $this->assertSame($response, (new RecordPageTiming)->handle(Request::create('/news'), fn () => $response));
    }

    /**
     * @return array<string,array{0:float,1:int}>
     */
    public static function durations(): array
    {
        return [
            'a second and a half' => [1.5, 1500],
            'rounded down' => [1.2344, 1234],
            'rounded up' => [1.2346, 1235],
            'nothing' => [0.0, 0],
        ];
    }

    #[DataProvider('durations')]
    public function test_the_duration_is_counted_from_the_start_of_the_request_in_milliseconds(float $seconds, int $milliseconds): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        $this->terminate(now()->getTimestamp() - $seconds);

        $this->assertSame($milliseconds, PageTiming::query()->value('duration_ms'));
    }

    public function test_a_request_without_a_start_time_counts_as_zero(): void
    {
        $request = Request::create('/news');
        $request->server->remove('REQUEST_TIME_FLOAT');

        (new RecordPageTiming)->terminate($request, new Response('<h1>News</h1>', 200, ['Content-Type' => 'text/html']));

        $this->assertSame(0, PageTiming::query()->value('duration_ms'));
    }

    public function test_a_hung_request_is_capped_at_ten_minutes(): void
    {
        $this->terminate(microtime(true) - 10000);

        $this->assertSame(600000, PageTiming::query()->value('duration_ms'));
    }

    public function test_a_start_in_the_future_counts_as_zero(): void
    {
        $this->terminate(microtime(true) + 100);

        $this->assertSame(0, PageTiming::query()->value('duration_ms'));
    }

    public function test_a_sample_rate_of_zero_measures_nothing(): void
    {
        config(['atrium.dashboard.timings.sample_rate' => 0.0]);

        $this->get('/news')->assertOk();

        $this->assertSame(0, PageTiming::query()->count());
    }

    public function test_without_a_sample_rate_every_page_is_measured(): void
    {
        config(['atrium.dashboard.timings' => ['enabled' => true]]);
        mt_srand($this->seedWhere(fn (): bool => mt_rand() / mt_getrandmax() > 0.001));

        $this->terminate(microtime(true));

        $this->assertSame(1, PageTiming::query()->count());
    }

    public function test_a_draw_equal_to_the_sample_rate_is_kept(): void
    {
        mt_srand(7);
        $draw = mt_rand() / mt_getrandmax();
        config(['atrium.dashboard.timings.sample_rate' => $draw]);

        mt_srand(7);
        $this->terminate(microtime(true));

        $this->assertSame(1, PageTiming::query()->count());
    }

    public function test_a_sample_rate_keeps_the_draws_at_or_under_it(): void
    {
        config(['atrium.dashboard.timings.sample_rate' => 0.5]);
        $under = $this->seedWhere(fn (): bool => mt_rand() / mt_getrandmax() <= 0.5);
        $over = $this->seedWhere(fn (): bool => mt_rand() / mt_getrandmax() > 0.5);

        mt_srand($over);
        $this->terminate(microtime(true));
        $this->assertSame(0, PageTiming::query()->count());

        mt_srand($under);
        $this->terminate(microtime(true));
        $this->assertSame(1, PageTiming::query()->count());
    }

    public function test_one_write_in_a_hundred_prunes_old_measurements(): void
    {
        config(['atrium.dashboard.timings.keep_days' => 7]);
        $this->travelTo(now()->subDays(8));
        PageTiming::query()->create(['path' => '/old', 'duration_ms' => 10]);
        $this->travelBack();
        $this->travelTo(now()->subDays(6));
        PageTiming::query()->create(['path' => '/recent', 'duration_ms' => 10]);
        $this->travelBack();

        mt_srand($this->seedWhere(fn (): bool => mt_rand(1, 100) !== 1));
        $this->terminate(microtime(true));
        $this->assertSame(['/old', '/recent', '/news'], PageTiming::query()->orderBy('id')->pluck('path')->all());

        mt_srand($this->seedWhere(fn (): bool => mt_rand(1, 100) === 1));
        $this->terminate(microtime(true));
        $this->assertSame(['/recent', '/news', '/news'], PageTiming::query()->orderBy('id')->pluck('path')->all());
    }

    public function test_without_a_setting_measurements_are_kept_for_thirty_days(): void
    {
        config(['atrium.dashboard.timings' => ['enabled' => true, 'sample_rate' => 1.0]]);
        $this->travelTo(now()->subHours(30 * 24 + 12));
        PageTiming::query()->create(['path' => '/too-old', 'duration_ms' => 10]);
        $this->travelBack();
        $this->travelTo(now()->subHours(30 * 24 - 12));
        PageTiming::query()->create(['path' => '/still-kept', 'duration_ms' => 10]);
        $this->travelBack();

        mt_srand($this->seedWhere(fn (): bool => mt_rand(1, 100) === 1));
        $this->terminate(microtime(true));

        $this->assertSame(['/still-kept', '/news'], PageTiming::query()->orderBy('id')->pluck('path')->all());
    }

    public function test_pruning_keeps_at_least_a_day(): void
    {
        config(['atrium.dashboard.timings.keep_days' => 0]);
        $this->travelTo(now()->subHours(25));
        PageTiming::query()->create(['path' => '/yesterday', 'duration_ms' => 10]);
        $this->travelBack();
        $this->travelTo(now()->subHours(12));
        PageTiming::query()->create(['path' => '/this-morning', 'duration_ms' => 10]);
        $this->travelBack();

        mt_srand($this->seedWhere(fn (): bool => mt_rand(1, 100) === 1));
        $this->terminate(microtime(true));

        $this->assertSame(['/this-morning', '/news'], PageTiming::query()->orderBy('id')->pluck('path')->all());
    }

    private function terminate(float $start): void
    {
        $request = Request::create('/news', 'GET', server: ['REQUEST_TIME_FLOAT' => $start]);
        $response = new Response('<h1>News</h1>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        (new RecordPageTiming)->terminate($request, $response);
    }

    /**
     * The first seed whose next draw passes the check, so a random branch
     * can be taken on purpose.
     */
    private function seedWhere(callable $check): int
    {
        for ($seed = 1; ; $seed++) {
            mt_srand($seed);

            if ($check()) {
                return $seed;
            }
        }
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\CacheDebug;
use Atrium\Admin\Http\Middleware\CachePages;
use Atrium\Admin\Http\Middleware\Clock;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

require_once __DIR__.'/../Fixtures/Clock/microtime.php';

/**
 * The debug line of local development: how many queries a page ran, how
 * many cards came from the cache, how long it took.
 */
class CacheDebugTest extends CacheTestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/queries', function () {
            DB::table('cache_books')->count();
            DB::table('cache_books')->count();
            DB::table('cache_books')->count();

            return response('<html><body>counted</body></html>');
        });

        $router->middleware('web')->get('/timed', function () {
            $connection = DB::connection();
            $events = $this->app['events'];
            $events->dispatch(new QueryExecuted('select 1', [], 12.5, $connection));
            $events->dispatch(new QueryExecuted('select 2', [], 7.5, $connection));

            return response('<html><body>timed</body></html>');
        });

        $router->middleware('web')->get('/quiet', fn () => response('<html><body>quiet</body></html>'));
        $router->middleware('web')->get('/books/{id}', fn (int $id) => response('<html><body>'.e((string) Book::cached($id)?->name).'</body></html>'));
        $router->middleware('web')->get('/two-bodies', fn () => response('<html><body><script>"</body>"</script></body></html>'));
        $router->middleware('web')->get('/upper', fn () => response('<HTML><BODY>shout</BODY></HTML>'));
        $router->middleware('web')->get('/fragment', fn () => response('<p>no body here</p>'));
        $router->middleware('web')->get('/data', fn () => response('{"html":"</body>"}', 200, ['Content-Type' => 'application/json']));
        $router->middleware('web')->get('/stream', fn () => response()->stream(function (): void {
            echo '<html><body>streamed</body></html>';
        }, 200, ['Content-Type' => 'text/html']));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The listener is added once for the process; a new application needs its own.
        (new ReflectionProperty(CacheDebug::class, 'listening'))->setValue(null, false);
        Clock::$instants = [];
        config(['app.debug' => true, 'atrium.cache.debug' => true]);
        $this->app['env'] = 'local';
    }

    protected function tearDown(): void
    {
        Clock::$instants = [];

        parent::tearDown();
    }

    private function line(string $uri): string
    {
        return (string) $this->get($uri)->assertOk()->headers->get(CacheDebug::HEADER);
    }

    /**
     * @return array<string,array{0:bool,1:bool,2:string,3:bool}>
     */
    public static function modes(): array
    {
        return [
            'local, debugging, on' => [true, true, 'local', true],
            'switched off' => [false, true, 'local', false],
            'not debugging' => [true, false, 'local', false],
            'not local' => [true, true, 'production', false],
            'nothing of it' => [false, false, 'production', false],
            'only debugging' => [false, true, 'production', false],
            'only the switch' => [true, false, 'production', false],
            'local but off and not debugging' => [false, false, 'local', false],
        ];
    }

    #[DataProvider('modes')]
    public function test_the_debug_line_is_only_for_local_development(bool $switch, bool $debug, string $environment, bool $expected): void
    {
        config(['atrium.cache.debug' => $switch, 'app.debug' => $debug]);
        $this->app['env'] = $environment;

        $this->assertSame($expected, CacheDebug::enabled());

        $response = $this->get('/quiet')->assertOk();

        if ($expected) {
            $response->assertHeader(CacheDebug::HEADER)->assertSee('data-atrium-debug', false);
        } else {
            $response->assertHeaderMissing(CacheDebug::HEADER)->assertDontSee('data-atrium-debug', false);
        }
    }

    public function test_the_switch_and_the_debug_mode_may_be_given_as_numbers(): void
    {
        config(['atrium.cache.debug' => 1, 'app.debug' => '1']);

        $this->assertTrue(CacheDebug::enabled());

        config(['atrium.cache.debug' => 0]);

        $this->assertFalse(CacheDebug::enabled());
    }

    public function test_the_debug_line_is_on_by_default_in_local_debugging(): void
    {
        $atrium = (array) config('atrium.cache');
        unset($atrium['debug']);
        config(['atrium.cache' => $atrium]);

        $this->assertTrue(CacheDebug::enabled());
    }

    public function test_the_debug_line_is_off_when_the_debug_mode_is_not_set_at_all(): void
    {
        $app = (array) config('app');
        unset($app['debug']);
        config(['app' => $app]);

        $this->assertFalse(CacheDebug::enabled());
    }

    public function test_it_counts_the_queries_a_page_ran(): void
    {
        $this->assertMatchesRegularExpression('/^queries=3 \(\d+\.\d ms\); /', $this->line('/queries'));
        $this->assertMatchesRegularExpression('/^queries=0 \(0\.0 ms\); /', $this->line('/quiet'));
    }

    public function test_the_queries_of_one_page_are_not_counted_twice_or_into_the_next_page(): void
    {
        $this->line('/queries');
        $this->line('/queries');

        $this->assertMatchesRegularExpression('/^queries=3 /', $this->line('/queries'));
        $this->assertMatchesRegularExpression('/^queries=0 /', $this->line('/quiet'));
    }

    public function test_it_adds_up_the_time_of_the_queries(): void
    {
        $this->assertMatchesRegularExpression('/^queries=2 \(20\.0 ms\); /', $this->line('/timed'));
        $this->assertMatchesRegularExpression('/^queries=2 \(20\.0 ms\); /', $this->line('/timed'));
        $this->assertMatchesRegularExpression('/^queries=0 \(0\.0 ms\); /', $this->line('/quiet'));
    }

    public function test_it_tells_the_hits_and_misses_of_the_record_cache(): void
    {
        [$book] = $this->books(1);

        $this->assertMatchesRegularExpression('/; cache hits=\d+ misses=[1-9]\d*; /', $this->line('/books/'.$book->id));
        $this->assertMatchesRegularExpression('/; cache hits=[1-9]\d* misses=0; /', $this->line('/books/'.$book->id));
    }

    public function test_it_tells_the_state_of_the_page_cache_in_lower_case(): void
    {
        $this->assertStringContainsString('; page cache=off; ', $this->line('/quiet'));

        config(['atrium.cache.pages.enabled' => true]);

        $this->assertStringContainsString('; page cache=miss; ', $this->line('/quiet'));
        $this->assertStringContainsString('; page cache=hit; ', $this->line('/quiet'));
    }

    public function test_it_tells_how_long_the_page_took_in_milliseconds(): void
    {
        Clock::$instants = [100.0, 100.5];

        $this->assertStringEndsWith('; 500.0 ms', $this->line('/quiet'));

        Clock::$instants = [10.0, 10.0025];

        $this->assertStringEndsWith('; 2.5 ms', $this->line('/quiet'));
    }

    public function test_the_line_is_put_into_the_page_just_before_its_end(): void
    {
        $response = $this->get('/quiet');
        $line = e((string) $response->headers->get(CacheDebug::HEADER));

        $this->assertSame(
            '<html><body>quiet<div data-atrium-debug style="position:fixed;left:0;bottom:0;z-index:2147483647;padding:2px 8px;font:12px/1.6 ui-monospace,monospace;background:#111;color:#eee;opacity:.85">Atrium: '.$line.'</div></body></html>',
            $response->getContent(),
        );
    }

    public function test_the_line_goes_before_the_last_closing_tag_whatever_its_case(): void
    {
        $this->assertMatchesRegularExpression('#^<html><body><script>"</body>"</script><div data-atrium-debug .*</div></body></html>$#', (string) $this->get('/two-bodies')->getContent());
        $this->assertMatchesRegularExpression('#^<HTML><BODY>shout<div data-atrium-debug .*</div></BODY></HTML>$#', (string) $this->get('/upper')->getContent());
    }

    public function test_a_page_with_no_closing_body_gets_the_header_only(): void
    {
        $response = $this->get('/fragment')->assertHeader(CacheDebug::HEADER);

        $this->assertSame('<p>no body here</p>', $response->getContent());
    }

    public function test_a_page_that_is_not_html_gets_the_header_only(): void
    {
        $response = $this->get('/data')->assertHeader(CacheDebug::HEADER);

        $this->assertSame('{"html":"</body>"}', $response->getContent());
    }

    public function test_a_streamed_page_gets_the_header_only(): void
    {
        $this->get('/stream')->assertHeader(CacheDebug::HEADER)->assertStreamedContent('<html><body>streamed</body></html>');
    }

    public function test_the_page_cache_header_stays_with_the_page(): void
    {
        config(['atrium.cache.pages.enabled' => true]);

        $this->get('/quiet')->assertHeader(CachePages::HEADER, 'MISS')->assertHeader(CacheDebug::HEADER);
    }
}

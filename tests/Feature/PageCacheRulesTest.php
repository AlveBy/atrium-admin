<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Http\Middleware\CachePages;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Which pages the page cache keeps and which it leaves alone, how a kept
 * page is named and what it is stored with, and what a CDN is told.
 */
class PageCacheRulesTest extends CacheTestCase
{
    /** @var array<string,int> how many times each route built its page */
    private array $built = [];

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $page = function (string $_, string $body = 'page', int $status = 200, array $headers = []): callable {
            return function () use ($body, $status, $headers) {
                $this->countBuild();

                return response('<html><body>'.$body.'</body></html>', $status, $headers);
            };
        };

        $router->middleware('web')->get('/', $page('root', 'home'));
        $router->middleware('web')->get('/page', $page('page', 'a page'));
        $router->middleware('web')->get('/deep/er/page', $page('deep', 'deep'));
        $router->middleware('web')->get('/missing', $page('missing', 'gone', 404));
        $router->middleware('web')->get('/moved', fn () => redirect('/page'));
        $router->middleware('web')->get('/download', $page('download', 'file', 200, ['Content-Disposition' => 'attachment; filename="a.html"']));
        $router->middleware('web')->get('/nostore', $page('nostore', 'secret', 200, ['Cache-Control' => 'no-store']));
        $router->middleware('web')->get('/nocache', $page('nocache', 'secret', 200, ['X-Atrium-No-Cache' => '1']));
        $router->middleware('web')->get('/charset', $page('charset', 'text', 200, ['Content-Type' => 'text/html; charset=iso-8859-1']));
        $router->middleware('web')->get('/literal', $page('literal', 'the placeholder is '.CachePages::TOKEN));
        $router->middleware('web')->get('/public-token', function () {
            return response('<html><body>'.csrf_token().'</body></html>', 200, ['Cache-Control' => 'public, max-age=3600']);
        });
        $router->middleware('web')->get('/json', function () {
            $this->countBuild();

            return response()->json(['body' => 'text']);
        });
        $router->middleware('web')->get('/own', function () {
            $this->countBuild();
            app(RecordCache::class)->dontKeepPage();

            return response('<html><body>for you alone</body></html>');
        });
        $router->middleware('web')->get('/queued', function () {
            $this->countBuild();
            Cookie::queue('seen', '1');

            return response('<html><body>queued</body></html>');
        });
        $router->middleware('web')->get('/empty', function () {
            $this->countBuild();

            return response('', 200, ['Content-Type' => 'text/html']);
        });
        $router->middleware('web')->get('/stream', function () {
            $this->countBuild();

            return response()->stream(function (): void {
                echo '<html><body>streamed</body></html>';
            }, 200, ['Content-Type' => 'text/html']);
        });
        $router->middleware('web')->get('/token', function () {
            $this->countBuild();

            return response('<html><body><input value="'.csrf_token().'"></body></html>');
        });
        // Outside the web group: no session.
        $router->middleware(CachePages::class)->get('/bare', function () {
            $this->countBuild();

            return response('<html><body>no session</body></html>');
        });
        foreach (['/backoffice', '/backoffice/orders', '/backofficer', '/cart', '/cart/items', '/account/orders'] as $path) {
            $router->middleware('web')->get($path, function () use ($path) {
                $this->countBuild();

                return response('<html><body>'.$path.'</body></html>');
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['atrium.cache.pages.enabled' => true]);
    }

    private function countBuild(): void
    {
        $path = request()->getPathInfo();
        $this->built[$path] = ($this->built[$path] ?? 0) + 1;
    }

    private function kept(string $uri, string $state, int $builds): void
    {
        $this->get($uri)->assertOk()->assertHeader(CachePages::HEADER, $state);
        $this->assertSame($builds, $this->built[strtok($uri, '?')] ?? null, $uri);
    }

    private function entry(string $key): mixed
    {
        return $this->app->make(CacheStore::class)->get('pages', $key);
    }

    private function without(string $key): void
    {
        $parts = explode('.', $key);
        $last = array_pop($parts);
        $parent = implode('.', $parts);
        $values = (array) config($parent);
        unset($values[$last]);
        config([$parent => $values]);
    }

    public function test_the_page_cache_is_off_unless_it_is_turned_on(): void
    {
        $this->without('atrium.cache.pages.enabled');

        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(2, $this->built['/page']);
    }

    public function test_the_page_cache_is_off_with_the_record_cache(): void
    {
        config(['atrium.cache.enabled' => false]);

        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(2, $this->built['/page']);
    }

    /**
     * @return array<string,array{0:array<string,string>}>
     */
    public static function requestsMadeByAScript(): array
    {
        return [
            'an ajax call' => [['X-Requested-With' => 'XMLHttpRequest']],
            'an Inertia visit' => [['X-Inertia' => 'true']],
            'a Livewire call' => [['X-Livewire' => 'true']],
        ];
    }

    /**
     * @param  array<string,string>  $headers
     */
    #[DataProvider('requestsMadeByAScript')]
    public function test_a_request_made_by_a_script_is_never_kept(array $headers): void
    {
        $this->get('/page', $headers)->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get('/page', $headers)->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(2, $this->built['/page']);
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function areasOfThePanel(): array
    {
        return [
            'the prefix itself' => ['backoffice', '/backoffice'],
            'below the prefix' => ['backoffice', '/backoffice/orders'],
            'a prefix with slashes around it' => ['/backoffice/', '/backoffice/orders'],
        ];
    }

    #[DataProvider('areasOfThePanel')]
    public function test_the_panel_is_never_kept(string $prefix, string $uri): void
    {
        config(['atrium.prefix' => $prefix]);

        $this->get($uri)->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get($uri)->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(2, $this->built[$uri]);
    }

    public function test_an_address_that_only_starts_like_the_panel_is_kept(): void
    {
        config(['atrium.prefix' => 'backoffice']);

        $this->kept('/backofficer', 'MISS', 1);
        $this->kept('/backofficer', 'HIT', 1);
    }

    public function test_a_panel_with_no_prefix_does_not_keep_the_site_away(): void
    {
        config(['atrium.prefix' => '']);

        $this->kept('/page', 'MISS', 1);
        $this->kept('/page', 'HIT', 1);
        $this->kept('/backoffice', 'MISS', 1);
    }

    public function test_pages_matching_the_except_list_are_never_kept(): void
    {
        config(['atrium.cache.pages.except' => ['/cart*', 'account/*']]);

        foreach (['/cart', '/cart/items', '/account/orders'] as $uri) {
            $this->get($uri)->assertOk()->assertHeaderMissing(CachePages::HEADER);
            $this->get($uri)->assertOk()->assertHeaderMissing(CachePages::HEADER);
            $this->assertSame(2, $this->built[$uri], $uri);
        }

        $this->kept('/page', 'MISS', 1);
    }

    public function test_one_except_pattern_may_be_given_as_a_string(): void
    {
        config(['atrium.cache.pages.except' => '/cart*']);

        $this->get('/cart')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get('/page')->assertOk()->assertHeader(CachePages::HEADER, 'MISS');
    }

    public function test_a_visitor_signed_in_to_the_site_never_gets_a_kept_page(): void
    {
        $this->kept('/page', 'MISS', 1);

        Auth::guard('web')->setUser(new GenericUser(['id' => 5]));

        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(2, $this->built['/page']);
    }

    public function test_an_administrator_signed_in_to_another_guard_never_gets_a_kept_page(): void
    {
        config(['atrium.auth.guard' => 'staff', 'auth.guards.staff' => ['driver' => 'session', 'provider' => 'users']]);
        $this->kept('/page', 'MISS', 1);

        // Not actingAs(): that makes the guard the default one, and the default guard is checked anyway.
        Auth::guard('staff')->setUser(new GenericUser(['id' => 5]));

        $this->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
    }

    public function test_a_guard_that_is_not_configured_signs_nobody_in(): void
    {
        config(['atrium.auth.guard' => 'ghost']);

        $this->kept('/page', 'MISS', 1);
        $this->kept('/page', 'HIT', 1);
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function sessionsWithSomethingForTheNextPage(): array
    {
        return [
            'validation errors' => [['errors' => 'something is wrong']],
            'flashed messages' => [['_flash' => ['old' => ['status'], 'new' => []], 'status' => 'saved']],
            'old input' => [['_old_input' => ['name' => 'typed']]],
        ];
    }

    /**
     * @param  array<string,mixed>  $session
     */
    #[DataProvider('sessionsWithSomethingForTheNextPage')]
    public function test_a_request_carrying_flashed_messages_or_old_input_is_not_kept(array $session): void
    {
        $this->withSession($session)->get('/page')->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->assertSame(1, $this->built['/page']);

        $this->flushSession();
        $this->get('/page')->assertOk()->assertHeader(CachePages::HEADER, 'MISS');
    }

    public function test_a_page_with_no_session_is_kept_too(): void
    {
        $this->kept('/bare', 'MISS', 1);
        $this->kept('/bare', 'HIT', 1);
        $this->get('/bare')->assertSee('no session');
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function pagesNeverKept(): array
    {
        return [
            'a missing page' => ['/missing'],
            'a redirect' => ['/moved'],
            'a download' => ['/download'],
            'a page that says no-store' => ['/nostore'],
            'a page that sends X-Atrium-No-Cache' => ['/nocache'],
            'a page made for this visitor alone' => ['/own'],
            'a page that sets a cookie' => ['/queued'],
            'a page that is not HTML' => ['/json'],
            'an empty page' => ['/empty'],
            'a streamed page' => ['/stream'],
        ];
    }

    #[DataProvider('pagesNeverKept')]
    public function test_a_page_that_must_not_be_kept_is_built_every_time(string $uri): void
    {
        $first = $this->get($uri);
        $second = $this->get($uri);

        $first->assertStatus($uri === '/moved' ? 302 : ($uri === '/missing' ? 404 : 200));
        $first->assertHeaderMissing(CachePages::HEADER);
        $second->assertHeaderMissing(CachePages::HEADER);
        // A redirect builds no page of its own, but it was not kept either.
        $this->assertSame($uri === '/moved' ? null : 2, $this->built[$uri] ?? null);
    }

    public function test_a_plain_page_is_kept(): void
    {
        $this->kept('/deep/er/page', 'MISS', 1);
        $this->kept('/deep/er/page', 'HIT', 1);
    }

    public function test_a_kept_page_is_named_by_site_address_and_sorted_query(): void
    {
        $this->get('/page?b=2&a=1')->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertIsArray($this->entry('page:1:'.hash('xxh128', 'http://localhost/page?a=1&b=2')));
        $this->kept('/page?a=1&b=2', 'HIT', 1);
        $this->get('/page?a=1&b=3')->assertHeader(CachePages::HEADER, 'MISS');
    }

    public function test_the_home_page_is_named_by_its_host_and_no_path(): void
    {
        $this->get('/')->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertIsArray($this->entry('page:1:'.hash('xxh128', 'http://localhost/?')));
    }

    public function test_the_same_path_on_another_host_is_another_page(): void
    {
        $this->get('http://one.test/page')->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('http://two.test/page')->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('http://one.test/page')->assertHeader(CachePages::HEADER, 'HIT');
    }

    public function test_a_page_is_named_by_the_site_the_request_is_in(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'domains' => ['shop.test'], 'active' => true]);

        $this->get('http://shop.test/page')->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertIsArray($this->entry('page:'.$site->id.':'.hash('xxh128', 'http://shop.test/page?')));
    }

    public function test_a_request_that_no_site_claimed_names_pages_with_zero(): void
    {
        $this->get('/bare')->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertIsArray($this->entry('page:0:'.hash('xxh128', 'http://localhost/bare?')));
    }

    public function test_a_site_not_saved_yet_names_pages_with_zero(): void
    {
        Site::query()->delete();
        $this->app->make(Sites::class)->flush();

        $this->get('/page')->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertIsArray($this->entry('page:0:'.hash('xxh128', 'http://localhost/page?')));
    }

    public function test_a_page_that_read_nothing_still_depends_on_the_whole_cache_and_its_site(): void
    {
        $builds = 0;
        $middleware = $this->app->make(CachePages::class);
        $handle = function () use ($middleware, &$builds) {
            $this->resetRequest();

            return $middleware->handle(Request::create('http://localhost/direct'), function () use (&$builds) {
                $builds++;

                return response('<html><body>direct</body></html>', 200, ['Content-Type' => 'text/html']);
            });
        };

        $this->assertSame('MISS', $handle()->headers->get(CachePages::HEADER));
        $this->assertSame(['all', 'site:0'], array_keys((array) $this->entry('page:0:'.hash('xxh128', 'http://localhost/direct?'))['versions']));

        $this->assertSame('HIT', $handle()->headers->get(CachePages::HEADER));

        $this->app->make(RecordCache::class)->flushAll();

        $this->assertSame('MISS', $handle()->headers->get(CachePages::HEADER));
        $this->assertSame(2, $builds);
    }

    public function test_a_damaged_entry_is_not_served_and_the_page_is_built_again_quietly(): void
    {
        Exceptions::fake();
        $key = 'page:0:'.hash('xxh128', 'http://localhost/bare?');
        $this->app->make(CacheStore::class)->put('pages', $key, ['body' => 'damaged', 'versions' => 'damaged', 'etag' => 'x', 'time' => 1, 'type' => 'text/html', 'token' => false], 600);

        $this->get('/bare')->assertOk()->assertSee('no session')->assertDontSee('damaged')->assertHeader(CachePages::HEADER, 'MISS');

        Exceptions::assertNothingReported();
        $this->assertIsArray($this->entry($key)['versions']);
    }

    public function test_a_kept_page_carries_what_it_showed_in_its_versions(): void
    {
        config(['atrium.cache.pages.depends_on' => ['Zed', 'Alpha']]);
        $site = Site::query()->firstOrFail();

        $this->get('/page');

        $versions = array_keys((array) $this->entry('page:'.$site->id.':'.hash('xxh128', 'http://localhost/page?'))['versions']);
        $this->assertContains('all', $versions);
        $this->assertContains('site:'.$site->id, $versions);
        $this->assertContains('list:Zed', $versions);
        $this->assertContains('list:Alpha', $versions);
        $this->assertLessThan(array_search('list:Zed', $versions, true), array_search('list:Alpha', $versions, true));
        $this->assertLessThan(array_search('site:'.$site->id, $versions, true), array_search('list:Zed', $versions, true));
    }

    public function test_one_dependency_may_be_given_as_a_string(): void
    {
        config(['atrium.cache.pages.depends_on' => 'Zed']);
        $site = Site::query()->firstOrFail();

        $this->get('/page')->assertOk()->assertHeader(CachePages::HEADER, 'MISS');

        $this->assertContains('list:Zed', array_keys((array) $this->entry('page:'.$site->id.':'.hash('xxh128', 'http://localhost/page?'))['versions']));
    }

    public function test_a_page_with_a_dependency_is_built_again_when_that_model_is_saved(): void
    {
        config(['atrium.cache.pages.depends_on' => [Site::class]]);
        $this->kept('/page', 'MISS', 1);
        $this->kept('/page', 'HIT', 1);

        Site::query()->firstOrFail()->update(['name' => 'Renamed']);

        $this->kept('/page', 'MISS', 2);
    }

    public function test_a_kept_page_is_stored_for_the_time_the_config_says(): void
    {
        $this->freezeTime();
        config(['atrium.cache.ttl.pages' => 100]);
        $this->kept('/page', 'MISS', 1);

        $this->travel(99)->seconds();
        $this->kept('/page', 'HIT', 1);

        $this->travel(1)->seconds();
        $this->kept('/page', 'MISS', 2);
    }

    public function test_a_kept_page_is_stored_for_ten_minutes_by_default(): void
    {
        $this->without('atrium.cache.ttl.pages');
        $this->freezeTime();
        $this->kept('/page', 'MISS', 1);

        $this->travel(599)->seconds();
        $this->kept('/page', 'HIT', 1);

        $this->travel(1)->seconds();
        $this->kept('/page', 'MISS', 2);
    }

    public function test_a_page_is_stored_for_at_least_a_second(): void
    {
        $this->freezeTime();
        config(['atrium.cache.ttl.pages' => 0]);
        $this->kept('/page', 'MISS', 1);
        $this->kept('/page', 'HIT', 1);

        $this->travel(1)->seconds();
        $this->kept('/page', 'MISS', 2);
    }

    public function test_a_kept_page_carries_its_etag_and_the_type_it_was_built_with(): void
    {
        $first = $this->get('/charset')->assertHeader(CachePages::HEADER, 'MISS');
        $second = $this->get('/charset')->assertHeader(CachePages::HEADER, 'HIT');

        $etag = '"'.sha1('<html><body>text</body></html>').'"';
        $this->assertSame($etag, $first->headers->get('ETag'));
        $this->assertSame($etag, $second->headers->get('ETag'));
        $this->assertSame('text/html; charset=iso-8859-1', $first->headers->get('Content-Type'));
        $this->assertSame('text/html; charset=iso-8859-1', $second->headers->get('Content-Type'));
        $second->assertSee('text');
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function matchingEtags(): array
    {
        return [
            'a strong one' => ['%s'],
            'a weak one' => ['W/%s'],
            'one in a list' => ['"other", %s'],
        ];
    }

    #[DataProvider('matchingEtags')]
    public function test_a_matching_etag_answers_not_modified(string $template): void
    {
        $etag = '"'.sha1('<html><body>a page</body></html>').'"';
        $this->get('/page');

        $this->get('/page', ['If-None-Match' => sprintf($template, $etag)])->assertStatus(304);
    }

    public function test_another_etag_gets_the_page(): void
    {
        $this->get('/page');

        $this->get('/page', ['If-None-Match' => '"something-else"'])->assertOk()->assertSee('a page');
        $this->get('/page', ['If-None-Match' => 'W/"something-else"'])->assertOk()->assertSee('a page');
    }

    public function test_a_page_without_a_token_may_be_shared_for_the_seconds_the_config_says(): void
    {
        config(['atrium.cache.pages.shared_max_age' => 120]);

        $this->get('/page');

        $this->assertSame('max-age=0, public, s-maxage=120', $this->get('/page')->headers->get('Cache-Control'));
    }

    public function test_a_page_is_shared_for_a_minute_by_default(): void
    {
        $this->without('atrium.cache.pages.shared_max_age');

        $this->assertSame('max-age=0, public, s-maxage=60', $this->get('/page')->headers->get('Cache-Control'));
    }

    public function test_a_negative_shared_time_means_not_shared(): void
    {
        config(['atrium.cache.pages.shared_max_age' => -5]);

        $this->assertSame('max-age=0, public, s-maxage=0', $this->get('/page')->headers->get('Cache-Control'));
    }

    public function test_a_page_with_a_form_token_is_private_on_the_first_visit_and_after(): void
    {
        $first = $this->withSession(['_token' => 'visitor-token'])->get('/token');
        $second = $this->get('/token');

        $this->assertSame('no-cache, private', $first->headers->get('Cache-Control'));
        $this->assertSame('no-cache, private', $second->headers->get('Cache-Control'));
    }

    public function test_a_page_with_a_form_token_is_private_even_when_it_asked_to_be_public(): void
    {
        $first = $this->withSession(['_token' => 'visitor-token'])->get('/public-token');
        $second = $this->get('/public-token');

        $this->assertSame('no-cache, private', $first->headers->get('Cache-Control'));
        $this->assertSame('no-cache, private', $second->headers->get('Cache-Control'));
    }

    public function test_the_placeholder_of_the_token_is_kept_as_text_in_a_page_with_no_token(): void
    {
        $this->get('/literal')->assertHeader(CachePages::HEADER, 'MISS');

        $this->withSession(['_token' => 'someone'])->get('/literal')
            ->assertHeader(CachePages::HEADER, 'HIT')
            ->assertSee(CachePages::TOKEN);
    }

    public function test_a_token_that_is_not_text_is_still_found_in_the_page(): void
    {
        $this->withSession(['_token' => 4242])->get('/token')->assertHeader(CachePages::HEADER, 'MISS');

        $this->flushSession();
        $this->withSession(['_token' => 'second'])->get('/token')
            ->assertHeader(CachePages::HEADER, 'HIT')
            ->assertSee('value="second"', false)
            ->assertDontSee('4242');
    }

    public function test_a_store_that_fails_costs_the_page_its_cache_not_the_visitor_his_page(): void
    {
        Exceptions::fake();
        $broken = new class(new ArrayStore) extends Repository
        {
            public function many(array $keys): array
            {
                throw new RuntimeException('the store is down');
            }

            public function putMany(array $values, $ttl = null): bool
            {
                throw new RuntimeException('the store is down');
            }
        };
        $this->app->instance(CacheStore::class, new CacheStore($broken, false));

        $this->get('/page')->assertOk()->assertSee('a page')->assertHeader(CachePages::HEADER, 'MISS');

        Exceptions::assertReportedCount(2);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'the store is down');
    }
}

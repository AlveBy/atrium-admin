<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Http\Middleware\CacheDebug;
use Atrium\Admin\Http\Middleware\CachePages;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Post;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;

/**
 * The full-page cache for anonymous visitors, the form token in a kept
 * page, the headers for a CDN, and the debug line.
 */
class PageCacheTest extends CacheTestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/books/{id}', function (int $id) {
            return response('<html><body><h1>'.e((string) Book::cached($id)?->name).'</h1></body></html>');
        });

        $router->middleware('web')->get('/order/{id}', function (int $id) {
            return response('<html><body><form><input name="_token" value="'.csrf_token().'"></form>'.e((string) Book::cached($id)?->name).'</body></html>');
        });

        $router->middleware('web')->get('/posts', function () {
            return response('<html><body>'.e((string) Post::query()->orderBy('id')->first()?->name).'</body></html>');
        });

        $router->middleware('web')->get('/cookie', function () {
            return response('<html><body>hi</body></html>')->cookie('seen', '1');
        });

        $router->middleware('web')->post('/books/{id}', fn () => response('<html><body>posted</body></html>'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['atrium.cache.pages.enabled' => true]);
    }

    public function test_it_is_off_by_default(): void
    {
        config(['atrium.cache.pages.enabled' => false]);
        [$book] = $this->books(1);

        $this->get('/books/'.$book->id)->assertOk()->assertHeaderMissing(CachePages::HEADER);
        $this->get('/books/'.$book->id)->assertOk()->assertHeaderMissing(CachePages::HEADER);
    }

    public function test_a_kept_page_is_served_without_a_query(): void
    {
        [$book] = $this->books(1);

        $this->get('/books/'.$book->id)->assertOk()->assertHeader(CachePages::HEADER, 'MISS')->assertSee('Book 1');

        [$queries, $response] = $this->countQueries(fn () => $this->get('/books/'.$book->id));

        $response->assertOk()->assertHeader(CachePages::HEADER, 'HIT')->assertSee('Book 1');
        $this->assertSame(0, $queries);
    }

    public function test_a_save_makes_the_page_build_again(): void
    {
        [$book] = $this->books(1);
        $this->get('/books/'.$book->id)->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('/books/'.$book->id)->assertHeader(CachePages::HEADER, 'HIT');

        $book->setTranslation('name', 'en', 'Renamed')->save();

        $this->get('/books/'.$book->id)->assertHeader(CachePages::HEADER, 'MISS')->assertSee('Renamed');
    }

    public function test_saving_a_model_the_page_read_directly_makes_it_build_again(): void
    {
        $post = Post::query()->create(['name' => 'First', 'active' => true]);
        $this->get('/posts')->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('/posts')->assertHeader(CachePages::HEADER, 'HIT');

        $post->update(['name' => 'Changed']);

        $this->get('/posts')->assertHeader(CachePages::HEADER, 'MISS')->assertSee('Changed');
    }

    public function test_clearing_a_module_clears_its_pages(): void
    {
        [$book] = $this->books(1);
        $this->get('/books/'.$book->id);
        DB::table('cache_books')->update(['name' => json_encode(['en' => 'Imported'])]);

        $this->app->make(RecordCache::class)->flushClass(Book::class);

        $this->get('/books/'.$book->id)->assertHeader(CachePages::HEADER, 'MISS')->assertSee('Imported');
    }

    public function test_a_kept_form_carries_the_current_visitors_token(): void
    {
        [$book] = $this->books(1);

        $first = $this->withSession(['_token' => 'token-of-the-first-visitor'])->get('/order/'.$book->id);
        $first->assertHeader(CachePages::HEADER, 'MISS')->assertSee('token-of-the-first-visitor');
        $this->flushSession();

        $second = $this->withSession(['_token' => 'token-of-the-second-visitor'])->get('/order/'.$book->id);

        $second->assertHeader(CachePages::HEADER, 'HIT')
            ->assertSee('token-of-the-second-visitor')
            ->assertDontSee('token-of-the-first-visitor')
            ->assertDontSee(CachePages::TOKEN);
        // A copy in a CDN would hand one visitor's token to the next.
        $this->assertStringContainsString('private', (string) $second->headers->get('Cache-Control'));
    }

    public function test_a_page_without_a_token_may_be_kept_by_a_cdn_and_answers_304(): void
    {
        [$book] = $this->books(1);

        $response = $this->get('/books/'.$book->id);
        $etag = (string) $response->headers->get('ETag');

        $this->assertNotSame('', $etag);
        $this->assertNotNull($response->headers->get('Last-Modified'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('s-maxage=60', (string) $response->headers->get('Cache-Control'));

        $this->get('/books/'.$book->id, ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_an_admin_never_gets_a_kept_page(): void
    {
        [$book] = $this->books(1);
        $this->get('/books/'.$book->id);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/books/'.$book->id)->assertOk()->assertHeaderMissing(CachePages::HEADER);
    }

    public function test_posts_queries_and_own_cookies_are_handled_apart(): void
    {
        [$book] = $this->books(1);

        $this->post('/books/'.$book->id)->assertHeaderMissing(CachePages::HEADER);
        $this->get('/books/'.$book->id.'?page=2')->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('/books/'.$book->id)->assertHeader(CachePages::HEADER, 'MISS');
        $this->get('/books/'.$book->id.'?page=2')->assertHeader(CachePages::HEADER, 'HIT');
        $this->get('/cookie')->assertHeaderMissing(CachePages::HEADER);
        $this->get('/cookie')->assertHeaderMissing(CachePages::HEADER);
    }

    public function test_the_debug_line_counts_queries_and_hits_in_local_mode(): void
    {
        config(['atrium.cache.pages.enabled' => false, 'app.debug' => true]);
        $this->app['env'] = 'local';
        [$book] = $this->books(1);

        $response = $this->get('/books/'.$book->id);

        $this->assertMatchesRegularExpression('/queries=\d+ .*cache hits=\d+ misses=[1-9]/', (string) $response->headers->get(CacheDebug::HEADER));
        $response->assertSee('data-atrium-debug', false);

        $this->app['env'] = 'testing';
        $this->get('/books/'.$book->id)->assertHeaderMissing(CacheDebug::HEADER);
    }

    public function test_an_import_past_eloquent_drops_everything_once(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);

        $this->app->make(RecordCache::class)->bulk(function (): void {
            DB::table('cache_books')->update(['price' => 5]);
        }, everything: true);
        $this->resetRequest();

        $this->assertSame(5, Book::cached($book->id)->price);
    }

    public function test_saving_a_site_rebuilds_its_cards(): void
    {
        $site = Site::query()->firstOrCreate(['code' => 'main'], ['name' => 'Main', 'active' => true, 'is_default' => true]);
        $this->app->make(Sites::class)->setCurrent($site);
        [$book] = $this->books(1);
        Book::cached($book->id);
        DB::table('cache_books')->update(['price' => 9]);

        $site->update(['name' => 'Main site']);
        $this->resetRequest();

        $this->assertSame(9, Book::cached($book->id)->price);
    }
}

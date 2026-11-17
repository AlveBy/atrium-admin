<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Http\Controllers\CacheController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\CacheScreen\Aardvark;
use Atrium\Admin\Tests\Fixtures\CacheScreen\Zebra;
use Atrium\Admin\Tests\Fixtures\Post;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The "Cache" section of the panel: it shows how the cache works and
 * clears it, for superadmins only.
 */
class CacheScreenTest extends CacheTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Added out of the alphabet: the screen sorts the modules by label.
        $app['config']->set('atrium.resources', [Post::class, Book::class, Zebra::class, Aardvark::class]);
    }

    private function remembered(string $name, int &$builds): string
    {
        return $this->app->make(RecordCache::class)->remember(Post::class, $name, function () use (&$builds): string {
            $builds++;

            return 'value';
        }, perSite: false);
    }

    private function totals(int $hits, int $misses): void
    {
        $repository = $this->app->make(CacheStore::class)->repository();
        $repository->put('atrium:stats:hits', $hits);
        $repository->put('atrium:stats:misses', $misses);
        // The panel's own request must not add to what is checked here.
        config(['atrium.cache.stats' => false]);
    }

    public function test_a_superadmin_sees_the_hit_rate_and_the_store(): void
    {
        [$book] = $this->books(1);
        $cache = $this->app->make(RecordCache::class);
        Book::cached($book->id);
        $cache->persistStats();
        $this->resetRequest();
        Book::cached($book->id);
        $cache->persistStats();
        // The panel's own request must not add to what is checked here.
        config(['atrium.cache.stats' => false]);
        ['hits' => $hits, 'misses' => $misses] = $cache->totals();
        $this->assertGreaterThan(0, $hits);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Cache/Index')
                ->where('status.enabled', true)
                ->where('status.driver', 'array')
                ->where('status.hits', $hits)
                ->where('status.misses', $misses)
                ->where('status.rate', fn ($rate): bool => abs((float) $rate - $hits * 100 / ($hits + $misses)) < 0.1)
                ->has('sites'));
    }

    public function test_clearing_everything_rebuilds_the_cards(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);
        DB::table('cache_books')->update(['price' => 7]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/cache/clear', ['scope' => 'all'])
            ->assertRedirect('/admin/cache')
            ->assertSessionHas('success');
        $this->resetRequest();

        $this->assertSame(7, Book::cached($book->id)->price);
    }

    public function test_resetting_the_counters_starts_them_from_zero(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);
        $cache = $this->app->make(RecordCache::class);
        $cache->persistStats();
        $this->assertGreaterThan(0, $cache->totals()['misses']);
        config(['atrium.cache.stats' => false]);

        $this->actingAs($this->admin(), 'admin')->post('/admin/cache/clear', ['scope' => 'stats'])->assertRedirect();

        $this->assertSame(['hits' => 0, 'misses' => 0], $cache->totals());
    }

    public function test_an_unknown_scope_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/cache/clear', ['scope' => 'everything'])
            ->assertSessionHasErrors('scope');
    }

    public function test_an_administrator_without_full_rights_cannot_open_it(): void
    {
        $editor = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->actingAs($editor, 'admin')->get('/admin/cache')->assertForbidden();
        $this->actingAs($editor, 'admin')->post('/admin/cache/clear', ['scope' => 'all'])->assertForbidden();
    }

    public function test_the_actions_of_the_screen_are_public(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')->assertOk();
        $this->post('/admin/cache/clear', ['scope' => 'all'])->assertRedirect();

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey(CacheController::class.'::index', $actions);
        $this->assertArrayHasKey(CacheController::class.'::clear', $actions);
    }

    public function test_the_modules_of_the_screen_are_the_cacheable_ones_sorted_by_label(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('modules', [
                    ['key' => 'aardvarks', 'label' => 'Aardvarks'],
                    ['key' => 'books', 'label' => 'Books'],
                    ['key' => 'zebras', 'label' => 'Zebras'],
                ]));
    }

    public function test_the_status_tells_how_the_cache_is_set_up(): void
    {
        $this->totals(0, 0);
        config(['atrium.cache.enabled' => 1, 'atrium.cache.pages.enabled' => 1]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.enabled', true)
                ->where('status.pages', true)
                ->where('status.tags', true)
                ->where('status.driver', 'array')
                ->where('status.entries', null)
                ->where('status.bytes', null));

        config(['atrium.cache.enabled' => 0, 'atrium.cache.pages.enabled' => 0]);

        $this->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.enabled', false)
                ->where('status.pages', false));
    }

    public function test_the_page_cache_is_off_on_the_screen_by_default(): void
    {
        $pages = (array) config('atrium.cache.pages');
        unset($pages['enabled']);
        config(['atrium.cache.pages' => $pages]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('status.pages', false));
    }

    public function test_the_hit_rate_is_a_percentage_of_all_reads_to_one_decimal(): void
    {
        $this->totals(1, 2);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.hits', 1)
                ->where('status.misses', 2)
                ->where('status.rate', 33.3));
    }

    public function test_the_hit_rate_is_empty_before_the_first_read(): void
    {
        $this->totals(0, 0);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('status.rate', null));
    }

    public function test_the_sites_of_the_screen_are_the_saved_ones(): void
    {
        $main = Site::query()->firstOrFail();
        $main->update(['name' => 'Main']);
        $shop = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'active' => false]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sites', [['id' => $main->id, 'name' => 'Main'], ['id' => $shop->id, 'name' => 'Shop']]));
    }

    public function test_a_panel_with_no_saved_site_lists_none(): void
    {
        Site::query()->delete();
        $this->app->make(Sites::class)->flush();

        $this->actingAs($this->admin(), 'admin')->get('/admin/cache')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('sites', []));
    }

    public function test_clearing_a_module_drops_only_its_cards_and_values(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);
        $builds = 0;
        $this->remembered('other', $builds);
        DB::table('cache_books')->update(['price' => 7]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/cache/clear', ['scope' => 'module', 'module' => 'books'])
            ->assertRedirect('/admin/cache')
            ->assertSessionHas('success');
        $this->resetRequest();

        $this->assertSame(7, Book::cached($book->id)->price);
        $this->remembered('other', $builds);
        $this->assertSame(1, $builds);
    }

    public function test_clearing_a_site_drops_only_that_sites_cards(): void
    {
        $first = Site::query()->firstOrFail();
        $second = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'active' => true]);
        $sites = $this->app->make(Sites::class);
        [$book] = $this->books(1);
        $sites->setCurrent($first);
        Book::cached($book->id);
        $sites->setCurrent($second);
        Book::cached($book->id);
        DB::table('cache_books')->update(['price' => 7]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/cache/clear', ['scope' => 'site', 'site' => (string) $first->id])
            ->assertRedirect('/admin/cache')
            ->assertSessionHas('success');
        $this->resetRequest();

        $sites->setCurrent($first);
        $this->assertSame(7, Book::cached($book->id)->price);
        $sites->setCurrent($second);
        $this->assertSame(1000, Book::cached($book->id)->price);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function refusedClearings(): array
    {
        return [
            'no scope' => [[], 'scope'],
            'an unknown scope' => [['scope' => 'everything'], 'scope'],
            'a module scope without a module' => [['scope' => 'module'], 'module'],
            'a module that does not exist' => [['scope' => 'module', 'module' => 'ghosts'], 'module'],
            'a module that is not cached' => [['scope' => 'module', 'module' => 'posts'], 'module'],
            'a site scope without a site' => [['scope' => 'site'], 'site'],
            'a site that is not a number' => [['scope' => 'site', 'site' => 'main'], 'site'],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    #[DataProvider('refusedClearings')]
    public function test_a_clearing_that_makes_no_sense_is_refused_and_drops_nothing(array $input, string $field): void
    {
        $builds = 0;
        $this->remembered('kept', $builds);

        $this->actingAs($this->admin(), 'admin')->post('/admin/cache/clear', $input)->assertSessionHasErrors($field);
        $this->remembered('kept', $builds);

        $this->assertSame(1, $builds);
    }

    public function test_clearing_everything_drops_every_value(): void
    {
        $builds = 0;
        $this->remembered('kept', $builds);

        $this->actingAs($this->admin(), 'admin')->post('/admin/cache/clear', ['scope' => 'all'])->assertRedirect('/admin/cache');
        $this->resetRequest();
        $this->remembered('kept', $builds);

        $this->assertSame(2, $builds);
    }
}

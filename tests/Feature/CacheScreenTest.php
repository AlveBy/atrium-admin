<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The "Cache" section of the panel: it shows how the cache works and
 * clears it, for superadmins only.
 */
class CacheScreenTest extends CacheTestCase
{
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
}

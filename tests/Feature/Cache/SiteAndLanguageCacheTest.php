<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\ScopedHandbook;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;

/**
 * The sites, the languages and the section paths that every request needs,
 * kept in the record cache until something behind them changes.
 */
final class SiteAndLanguageCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PublicUrl::forgetPaths();
    }

    protected function tearDown(): void
    {
        PublicUrl::forgetPaths();

        parent::tearDown();
    }

    private function sites(): Sites
    {
        return $this->app->make(Sites::class);
    }

    private function languages(): ContentLanguages
    {
        return $this->app->make(ContentLanguages::class);
    }

    private function addGerman(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
    }

    private function addGermanSite(): Site
    {
        $this->addGerman();

        return Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
    }

    /**
     * What the record cache holds under a name, or the given marker.
     */
    private function kept(string|array $classes, string $key): mixed
    {
        return $this->app->make(RecordCache::class)->remember($classes, $key, static fn (): string => 'nothing kept', perSite: false);
    }

    /**
     * A request that starts afresh: nothing read yet, nothing remembered.
     */
    private function forgetInRequest(): void
    {
        $this->app->make(RecordCache::class)->reset();
        $this->sites()->forgetCurrent();
        (new ReflectionProperty(Sites::class, 'all'))->setValue($this->sites(), null);
    }

    public function test_the_sites_are_kept_for_every_site_and_come_back_as_sites(): void
    {
        $this->addGermanSite();
        $this->forgetInRequest();

        $all = $this->sites()->all();

        $this->assertSame(['main', 'de'], $all->pluck('code')->all());
        $this->assertSame(['example.de'], $all->last()->domains);
        $this->assertTrue($all->last()->exists);
        $kept = $this->kept(Site::class, 'sites');
        $this->assertIsArray($kept);
        $this->assertSame(['main', 'de'], array_column($kept, 'code'));
    }

    public function test_the_sites_are_read_from_the_cache_while_nothing_has_changed(): void
    {
        $this->addGermanSite();
        $this->forgetInRequest();
        $this->sites()->all();
        $this->forgetInRequest();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->sites()->all();

        $this->assertSame(0, $queries);
    }

    public function test_flushing_the_sites_reads_them_anew_from_the_database(): void
    {
        $this->forgetInRequest();
        $this->assertSame('main', $this->sites()->all()->first()->code);

        // Changed behind the models' back.
        DB::table((new Site)->getTable())->where('code', 'main')->update(['name' => 'Renamed']);
        $this->forgetInRequest();
        $this->assertNotSame('Renamed', $this->sites()->all()->first()->name);

        $this->sites()->flush();

        $this->assertSame('Renamed', $this->sites()->all()->first()->name);
    }

    public function test_the_languages_are_kept_for_every_site_and_come_back_as_languages(): void
    {
        $this->addGerman();
        $this->languages()->flush();

        $codes = $this->languages()->codes();

        $this->assertSame(['en', 'de'], $codes);
        $this->assertTrue($this->languages()->all()->first()->exists);
        $this->assertTrue($this->languages()->all()->first()->is_default);
        $kept = $this->kept(Language::class, 'languages');
        $this->assertIsArray($kept);
        $this->assertSame(['en', 'de'], array_column($kept, 'code'));
    }

    public function test_flushing_the_languages_reads_them_anew_from_the_database(): void
    {
        $this->assertSame(['en'], $this->languages()->codes());

        // Added behind the models' back.
        DB::table((new Language)->getTable())->insert(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200, 'active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->languages()->forgetActive();

        // Forgotten for the request, but the cache still has what it had.
        $this->assertSame(['en'], $this->languages()->codes());

        $this->languages()->flush();

        $this->assertSame(['en', 'de'], $this->languages()->codes());
    }

    public function test_another_site_becoming_current_picks_its_languages_from_the_kept_ones(): void
    {
        $german = $this->addGermanSite();
        $this->languages()->flush();
        $this->assertSame(['en', 'de'], $this->languages()->codes());

        $this->sites()->setCurrent($german);

        $this->assertSame(['de'], $this->languages()->codes());

        $this->sites()->forgetCurrent();

        $this->assertSame(['en', 'de'], $this->languages()->codes());
    }

    public function test_switching_sites_does_not_flush_what_is_kept(): void
    {
        $german = $this->addGermanSite();
        $this->languages()->flush();
        $this->languages()->codes();
        DB::table((new Language)->getTable())->where('code', 'de')->update(['name' => 'Changed behind the back']);

        $this->sites()->setCurrent($german);
        $this->sites()->forgetCurrent();

        $this->assertSame('Deutsch', $this->languages()->find('de')->name);
    }

    public function test_section_paths_are_kept_for_every_site_under_the_name_of_the_tree(): void
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $italy = Handbook::query()->create(['name' => 'Italy', 'code' => 'italy', 'parent_id' => $tours->id]);

        $paths = PublicUrl::sectionPaths(Handbook::class);

        $this->assertSame('tours/italy', $paths[$italy->id]);
        $kept = $this->kept([Handbook::class, PublicUrl::class], 'section-paths:'.Handbook::class.'|parent_id');
        $this->assertSame($paths, $kept);
        $this->assertSame('nothing kept', $this->kept([Handbook::class, PublicUrl::class], 'section-paths:'));
        $this->assertSame('nothing kept', $this->kept([Handbook::class, PublicUrl::class], Handbook::class.'|parent_id'));
        $this->assertSame('nothing kept', $this->kept([Handbook::class, PublicUrl::class], Handbook::class.'|parent_idsection-paths:'));
    }

    public function test_section_paths_read_twice_in_a_request_ask_the_cache_once(): void
    {
        Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $this->app->make(RecordCache::class)->reset();

        PublicUrl::sectionPaths(Handbook::class);
        $asked = $this->app->make(RecordCache::class)->requestStats();
        PublicUrl::sectionPaths(Handbook::class);

        $this->assertSame(1, $asked['hits'] + $asked['misses']);
        $this->assertSame($asked, $this->app->make(RecordCache::class)->requestStats());
    }

    public function test_section_paths_are_dropped_when_a_section_of_the_tree_is_saved(): void
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
        $this->forgetPathsOfThisRequest();

        // The cache holds the tree until a section of it is saved.
        DB::table('articles')->where('id', $tours->id)->update(['code' => 'trips']);
        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
        $this->forgetPathsOfThisRequest();

        $tours->refresh()->update(['name' => 'Trips']);
        $this->app->make(RecordCache::class)->reset();

        $this->assertSame('trips', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
    }

    public function test_section_paths_stay_while_a_record_of_another_class_is_saved(): void
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
        DB::table('articles')->where('id', $tours->id)->update(['code' => 'trips']);

        Post::query()->create(['name' => 'Elsewhere']);
        $this->forgetPathsOfThisRequest();

        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
    }

    public function test_forgetting_the_paths_drops_the_kept_ones_too(): void
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
        DB::table('articles')->where('id', $tours->id)->update(['code' => 'trips']);
        $this->forgetPathsOfThisRequest();
        $this->assertSame('tours', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);

        PublicUrl::forgetPaths();

        $this->assertSame('trips', PublicUrl::sectionPaths(Handbook::class)[$tours->id]);
    }

    public function test_the_sections_a_scope_hides_are_still_in_the_paths_of_their_children(): void
    {
        $secret = ScopedHandbook::query()->withoutGlobalScopes()->create(['name' => 'Secret', 'code' => 'secret']);
        $child = ScopedHandbook::query()->withoutGlobalScopes()->create(['name' => 'Child', 'code' => 'child', 'parent_id' => $secret->id]);

        $paths = PublicUrl::sectionPaths(ScopedHandbook::class);

        $this->assertSame('secret/child', $paths[$child->id]);
        $this->assertSame('secret', $paths[$secret->id]);
    }

    /**
     * What a new request starts with: the paths of the last one forgotten
     * (the cache keeps its own).
     */
    private function forgetPathsOfThisRequest(): void
    {
        (new ReflectionProperty(PublicUrl::class, 'pathCache'))->setValue(null, []);
        $this->app->make(RecordCache::class)->reset();
    }
}

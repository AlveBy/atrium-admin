<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Columns\Column;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SiteAndLanguageModelsTest extends TestCase
{
    /**
     * @param  array<int,Column>  $columns
     * @return array<int,array<string,mixed>>
     */
    private function columns(array $columns): array
    {
        return array_map(static fn (Column $column): array => array_intersect_key($column->toArray(), array_flip(['name', 'type', 'label', 'sortable', 'searchable', 'width', 'format'])), $columns);
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->firstOrFail();
    }

    public function test_the_first_site_becomes_the_default_one_and_is_switched_on(): void
    {
        Site::query()->update(['is_default' => false]);

        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'is_default' => false, 'active' => false]);

        $this->assertTrue($site->is_default);
        $this->assertTrue($site->active);
    }

    public function test_another_site_stays_as_it_was_made(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop', 'is_default' => false, 'active' => false]);

        $this->assertFalse($site->fresh()?->is_default);
        $this->assertFalse($site->fresh()?->active);
        $this->assertTrue($this->main()->is_default);
    }

    public function test_a_new_default_site_takes_the_place_of_the_old_one(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'domains' => ['shop.example.com'], 'is_default' => true, 'active' => false]);

        $this->assertTrue($site->fresh()?->is_default);
        $this->assertTrue($site->fresh()?->active);
        $this->assertFalse($this->main()->is_default);
        $this->assertSame(1, Site::query()->where('is_default', true)->count());
    }

    public function test_the_code_of_a_saved_site_cannot_change(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop']);

        $site->update(['code' => 'store', 'name' => 'Store']);

        $this->assertSame('shop', $site->fresh()?->code);
        $this->assertSame('Store', $site->fresh()?->name);
    }

    public function test_an_empty_default_language_is_none(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop', 'default_language' => '']);

        $this->assertNull($site->fresh()?->default_language);
        $this->assertNull($site->fresh()?->languages);
    }

    public function test_a_default_language_outside_the_site_languages_gives_way_to_the_first(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop', 'languages' => ['de', '', 'en', 'de'], 'default_language' => 'fr']);
        $this->assertSame(['de', 'en'], $site->fresh()?->languages);
        $this->assertSame('de', $site->fresh()?->default_language);

        $kept = Site::query()->create(['code' => 'blog', 'name' => 'Blog', 'folder' => 'blog', 'languages' => ['de', 'en'], 'default_language' => 'en']);
        $this->assertSame('en', $kept->fresh()?->default_language);
    }

    public function test_a_deleted_site_is_forgotten_by_every_cache(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop']);
        $sites = app(Sites::class);
        $languages = app(ContentLanguages::class);
        $this->assertNotNull($sites->findByCode('shop'));
        $this->assertFalse($languages->has('de'));
        $version = Cache::get('atrium.sitemap.version');

        // Changed behind the models' back: the caches still hold the old state.
        DB::table('admin_languages')->insert(['code' => 'de', 'name' => 'Deutsch', 'active' => true, 'is_default' => false, 'sort' => 200]);
        $this->assertFalse($languages->has('de'));

        $site->delete();

        $this->assertNull($sites->findByCode('shop'));
        $this->assertTrue($languages->has('de'));
        $this->assertNotSame($version, Cache::get('atrium.sitemap.version'));
    }

    public function test_a_deleted_language_is_forgotten_by_every_cache(): void
    {
        $language = Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $languages = app(ContentLanguages::class);
        $this->assertTrue($languages->has('de'));
        $version = Cache::get('atrium.sitemap.version');

        $language->delete();

        $this->assertFalse($languages->has('de'));
        $this->assertNotSame($version, Cache::get('atrium.sitemap.version'));
    }

    public function test_the_holds_path_of_a_site_without_a_folder_is_always_false(): void
    {
        $this->assertFalse($this->main()->holdsPath('/'));
        $this->assertFalse($this->main()->holdsPath('/shop'));
        $this->assertFalse((new Site(['folder' => '']))->holdsPath('/'));
        $this->assertTrue((new Site(['folder' => 'shop']))->holdsPath('/shop/chairs'));
        $this->assertFalse((new Site(['folder' => 'shop']))->holdsPath('/shop2'));
    }

    public function test_the_admin_list_of_sites(): void
    {
        $this->assertSame('Sites', Site::getAdminLabel());
        $this->assertSame('sites', Site::getAdminRoutePrefix());
        $this->assertSame('Add site', Site::getAdminCreateLabel());
        $this->assertSame('sort', Site::getDefaultSortColumn());
        $this->assertSame('asc', Site::getDefaultSortDirection());
        $this->assertSame([
            ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'code', 'type' => 'text', 'label' => 'Code', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'folder', 'type' => 'text', 'label' => 'Folder', 'sortable' => true, 'searchable' => false, 'width' => null],
            ['name' => 'active', 'type' => 'boolean', 'label' => 'Active', 'sortable' => false, 'searchable' => false, 'width' => null],
            ['name' => 'is_default', 'type' => 'boolean', 'label' => 'Default site', 'sortable' => false, 'searchable' => false, 'width' => null],
            ['name' => 'sort', 'type' => 'text', 'label' => 'Sort order', 'sortable' => true, 'searchable' => false, 'width' => null],
        ], $this->columns(Site::getAdministrationListColumns()));
    }

    public function test_the_admin_list_of_languages(): void
    {
        $this->assertSame('Languages', Language::getAdminLabel());
        $this->assertSame('languages', Language::getAdminRoutePrefix());
        $this->assertSame('Add language', Language::getAdminCreateLabel());
        $this->assertSame('sort', Language::getDefaultSortColumn());
        $this->assertSame('asc', Language::getDefaultSortDirection());
        $this->assertSame([
            ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'code', 'type' => 'text', 'label' => 'Code', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'active', 'type' => 'boolean', 'label' => 'Active', 'sortable' => false, 'searchable' => false, 'width' => null],
            ['name' => 'is_default', 'type' => 'boolean', 'label' => 'Default', 'sortable' => false, 'searchable' => false, 'width' => null],
            ['name' => 'sort', 'type' => 'text', 'label' => 'Sort order', 'sortable' => true, 'searchable' => false, 'width' => null],
        ], $this->columns(Language::getAdministrationListColumns()));
    }

    public function test_the_admin_list_of_users(): void
    {
        $this->assertSame([
            ['name' => 'id', 'type' => 'text', 'label' => 'ID', 'sortable' => true, 'searchable' => false, 'width' => '80px'],
            ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'email', 'type' => 'text', 'label' => 'Email', 'sortable' => true, 'searchable' => true, 'width' => null],
            ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active', 'sortable' => true, 'searchable' => false, 'width' => null],
            ['name' => 'is_superadmin', 'type' => 'boolean', 'label' => 'Superadmin', 'sortable' => true, 'searchable' => false, 'width' => null],
            ['name' => 'created_at', 'type' => 'date', 'label' => 'Created', 'sortable' => true, 'searchable' => false, 'width' => null, 'format' => 'd.m.Y H:i'],
        ], $this->columns(AdminUser::getAdministrationListColumns()));
    }
}

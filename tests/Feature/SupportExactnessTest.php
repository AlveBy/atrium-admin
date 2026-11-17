<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\EnterSiteFolder;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Assets;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Locales;
use Atrium\Admin\Support\MediaUrl;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Support\Themes;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Section;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The exact values of the small services: what they return when the config
 * is missing, odd or half filled.
 */
final class SupportExactnessTest extends TestCase
{
    // ---- Assets ---------------------------------------------------------------------------------

    public function test_while_the_dev_server_runs_the_tags_point_to_it_and_name_the_app_script(): void
    {
        $this->withVite();
        $hot = public_path('vendor/atrium/hot');
        File::ensureDirectoryExists(dirname($hot));
        File::put($hot, 'http://localhost:5199');

        try {
            $tags = (string) Assets::tags();
        } finally {
            File::delete($hot);
        }

        $this->assertStringContainsString('src="http://localhost:5199/@vite/client"', $tags);
        $this->assertStringContainsString('src="http://localhost:5199/resources/js/app.js"', $tags);
    }

    // ---- ContentLanguages ----------------------------------------------------------------------

    public function test_the_stand_in_language_of_a_fresh_install_is_active(): void
    {
        config(['app.locale' => 'de']);
        Language::query()->delete();

        $language = (new ContentLanguages)->all()->sole();

        $this->assertTrue($language->active);
        $this->assertTrue($language->is_default);
        $this->assertSame('de', $language->code);
    }

    public function test_a_site_default_language_goes_first_and_the_others_keep_their_order(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);
        Language::query()->create(['code' => 'pl', 'name' => 'Polski', 'sort' => 400]);
        Site::query()->where('code', 'main')->update(['default_language' => 'fr']);
        app(Sites::class)->flush();

        $this->assertSame(['fr', 'en', 'de', 'pl'], app(ContentLanguages::class)->codes());
        $this->assertSame('fr', app(ContentLanguages::class)->default());
    }

    public function test_with_no_default_language_named_the_panel_default_goes_first(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);

        $this->assertSame(['en', 'de', 'fr'], app(ContentLanguages::class)->codes());

        // The site shows only two of them, the panel default is not among them: the first by order.
        Site::query()->where('code', 'main')->update(['languages' => ['fr', 'de']]);
        app(Sites::class)->flush();

        $this->assertSame(['de', 'fr'], (new ContentLanguages)->codes());
    }

    // ---- Locales -------------------------------------------------------------------------------

    public function test_without_a_locale_list_in_the_config_the_panel_is_english_only(): void
    {
        $this->app['config']->set('atrium', Arr::except(config('atrium'), 'locales'));
        $locales = new Locales;

        $this->assertSame(['en' => 'English'], $locales->available());
        $this->assertTrue($locales->isAvailable('en'));
    }

    public function test_a_locale_is_available_only_when_it_is_listed(): void
    {
        $locales = new Locales;

        $this->assertTrue($locales->isAvailable('ru'));
        $this->assertFalse($locales->isAvailable('zz'));
        $this->assertFalse($locales->isAvailable(null));
        $this->assertFalse($locales->isAvailable(''));
    }

    public function test_a_default_locale_that_is_not_a_string_in_the_config_is_not_taken(): void
    {
        config(['atrium.locale' => 7]);

        $this->assertSame('en', (new Locales)->resolve(Request::create('/'), null));
    }

    public function test_the_dictionary_skips_validation_and_fills_the_gaps_of_a_language_from_english(): void
    {
        $dictionary = (new Locales)->dictionary('ru');

        $this->assertArrayHasKey('common.save', $dictionary);
        $this->assertSame([], array_filter(array_keys($dictionary), static fn (string $key): bool => str_starts_with($key, 'validation.')));

        // A language that has only one line of a file: the rest comes from English.
        app('translator')->addLines(['common.save' => 'Speichern'], 'xx', 'atrium');
        $partial = (new Locales)->dictionary('xx');

        $english = (new Locales)->dictionary('en');
        $this->assertArrayHasKey('common.cancel', $english);
        $this->assertSame($english['common.cancel'], $partial['common.cancel']);
        $this->assertSame('Speichern', $partial['common.save']);
    }

    public function test_a_language_with_no_lang_files_gives_the_english_dictionary_and_nothing_else(): void
    {
        $this->assertSame((new Locales)->dictionary('en'), (new Locales)->dictionary('qq'));
    }

    public function test_the_validation_group_is_not_part_of_the_groups_of_the_dictionary_but_is_a_group(): void
    {
        $groups = (new Locales)->groups();

        $this->assertContains('validation', $groups);
        $this->assertContains('common', $groups);
    }

    public function test_a_regional_browser_language_finds_the_plain_language_of_the_panel(): void
    {
        config(['atrium.locales' => ['en' => 'English', 'pt' => 'Português']]);

        $this->assertSame('pt', (new Locales)->resolve(Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'pt-BR']), null));
        $this->assertSame('en', (new Locales)->resolve(Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA']), null));
    }

    // ---- MediaUrl ------------------------------------------------------------------------------

    public function test_only_an_address_that_starts_with_a_scheme_or_slashes_is_taken_as_is(): void
    {
        $this->assertSame('http://localhost/storage/a//b.jpg', MediaUrl::to('a//b.jpg'));
        $this->assertSame('http://localhost/storage/see-https://x.jpg', MediaUrl::to('see-https://x.jpg'));
        $this->assertSame('HTTPS://CDN.example.com/a.jpg', MediaUrl::to('HTTPS://CDN.example.com/a.jpg'));
        $this->assertSame('Http://cdn.example.com/a.jpg', MediaUrl::to('Http://cdn.example.com/a.jpg'));
    }

    // ---- Menu ----------------------------------------------------------------------------------

    public function test_a_menu_that_is_not_an_array_in_the_config_is_an_empty_menu(): void
    {
        config(['atrium.menu' => null]);

        $routes = array_column(app(Menu::class)->items(), 'route');

        $this->assertContains('admin.posts.index', $routes);
    }

    // ---- PublicUrl ------------------------------------------------------------------------------

    public function test_a_parent_column_from_the_config_is_kept_as_text(): void
    {
        config()->set('atrium.public_urls.'.Post::class, ['prefix' => 'a', 'tree' => Handbook::class, 'parent' => 'x', 'parent_column' => 123]);

        $this->assertSame('123', PublicUrl::ruleFor(Post::class)['parent_column']);
    }

    public function test_the_paths_of_two_trees_and_two_parent_columns_are_kept_apart(): void
    {
        PublicUrl::forgetPaths();
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        Handbook::query()->create(['name' => 'Italy', 'code' => 'italy', 'parent_id' => $tours->id]);
        $root = Section::query()->create(['name' => 'Root']);
        DB::table('sections')->where('id', $root->id)->update(['parent_id' => null]);

        $handbook = PublicUrl::sectionPaths(Handbook::class);
        $other = PublicUrl::sectionPaths(Section::class);

        $this->assertSame(['tours', 'tours/italy'], array_values($handbook));
        $this->assertNotSame($handbook, $other);
        $this->assertCount(1, $other);

        // The same tree read by another column is another answer, not the cached one.
        $this->assertSame(['tours', 'italy'], array_values(PublicUrl::sectionPaths(Handbook::class, 'section_parent')));
        $this->assertSame(['tours', 'tours/italy'], array_values(PublicUrl::sectionPaths(Handbook::class)));
        PublicUrl::forgetPaths();
    }

    public function test_a_section_without_a_code_adds_nothing_to_the_path_of_its_children(): void
    {
        PublicUrl::forgetPaths();
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $hidden = Handbook::query()->create(['name' => 'Hidden', 'parent_id' => $tours->id]);
        $hidden->forceFill(['code' => null])->saveQuietly();
        $deep = Handbook::query()->create(['name' => 'Deep', 'code' => 'deep', 'parent_id' => $hidden->id]);

        $paths = PublicUrl::sectionPaths(Handbook::class);

        $this->assertSame('tours', $paths[$hidden->id]);
        $this->assertSame('tours/deep', $paths[$deep->id]);
        PublicUrl::forgetPaths();
    }

    // ---- Sites ---------------------------------------------------------------------------------

    public function test_the_sites_are_read_once_and_the_default_one_is_the_first(): void
    {
        $sites = new Sites;

        $this->assertSame($sites->all(), $sites->all());
        $this->assertSame('main', $sites->default()->code);
    }

    public function test_a_site_in_a_folder_with_no_domains_at_all_is_open_to_every_host(): void
    {
        $shop = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop']);
        DB::table('admin_sites')->where('id', $shop->id)->update(['domains' => null]);
        app(Sites::class)->flush();

        $this->assertSame(['shop'], app(Sites::class)->folderSites('anything.test')->pluck('code')->all());
    }

    public function test_the_site_that_names_the_host_wins_in_the_same_folder(): void
    {
        Site::query()->create(['code' => 'any', 'name' => 'Any', 'folder' => 'shop']);
        Site::query()->create(['code' => 'named', 'name' => 'Named', 'folder' => 'shop', 'domains' => ['b.test']]);

        $this->assertSame(['named'], app(Sites::class)->folderSites('b.test')->pluck('code')->all());
        $this->assertSame(['any'], app(Sites::class)->folderSites('a.test')->pluck('code')->all());
    }

    public function test_the_root_address_is_cut_only_at_a_folder_not_at_the_end_of_a_host_name(): void
    {
        $request = Request::create('http://myshop/catalog', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/index.php',
            'PHP_SELF' => '/index.php',
        ]);
        $request->attributes->set(EnterSiteFolder::FOLDER, 'shop');

        $this->assertSame('http://myshop', Sites::rootUrl($request));
    }

    public function test_picking_a_site_and_forgetting_it_changes_the_content_languages_at_once(): void
    {
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);
        $fr = Site::query()->create(['code' => 'fr', 'name' => 'France', 'languages' => ['fr']]);
        $sites = app(Sites::class);
        $sites->flush();
        $languages = app(ContentLanguages::class);

        $this->assertSame(['en', 'fr'], $languages->codes());

        $sites->setCurrent($fr);
        $this->assertSame(['fr'], $languages->codes());

        $sites->forgetCurrent();
        $this->assertSame(['en', 'fr'], $languages->codes());
    }

    // ---- Themes --------------------------------------------------------------------------------

    public function test_a_theme_of_the_account_is_taken_only_when_it_is_one_of_the_choices(): void
    {
        $themes = new Themes;
        $user = new AdminUser;

        $this->assertSame($themes->default(), $themes->choiceFor(null));
        $this->assertSame($themes->default(), $themes->choiceFor($user));

        $user->theme = 'system';
        $this->assertSame('system', $themes->choiceFor($user));

        $user->theme = 'nonsense';
        $this->assertSame($themes->default(), $themes->choiceFor($user));
    }
}

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
use Atrium\Admin\Modules\DemoSeeder;
use Atrium\Admin\Modules\ModuleSettings;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Support\Assets;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\MediaUrl;
use Atrium\Admin\Support\Menu;
use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

final class SupportEdgesTest extends TestCase
{
    private function editor(): AdminUser
    {
        return AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
    }

    /**
     * Point the models at an empty database for the length of a callback:
     * a fresh install before its migrations ran.
     */
    private function withoutTables(callable $callback): void
    {
        $default = config('database.default');
        config(['database.connections.unmigrated' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        config(['database.default' => 'unmigrated']);

        try {
            $callback();
        } finally {
            config(['database.default' => $default]);
            DB::purge('unmigrated');
        }
    }

    public function test_a_menu_group_whose_entries_are_all_closed_is_left_out(): void
    {
        config(['atrium.menu' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['label' => 'Content', 'children' => [['label' => 'Blog', 'route' => 'admin.posts.index']]],
            ['label' => 'Help', 'route' => 'admin.dashboard', 'children' => [['label' => 'Blog', 'route' => 'admin.posts.index']]],
        ]]);
        $menu = app(Menu::class);

        $forEditor = $menu->items($this->editor());
        $this->assertNotContains('Content', array_column($forEditor, 'label'));
        // A group that opens a page of its own stays, without the closed entries.
        $help = array_values(array_filter($forEditor, static fn (array $item): bool => $item['label'] === 'Help'))[0];
        $this->assertSame([], $help['children']);

        $forAdmin = $menu->items($this->admin());
        $content = array_values(array_filter($forAdmin, static fn (array $item): bool => $item['label'] === 'Content'))[0];
        $this->assertSame([['label' => 'Blog', 'route' => 'admin.posts.index']], $content['children']);
    }

    /**
     * @return array<string,array{mixed,?string}>
     */
    public static function mediaValues(): array
    {
        return [
            'array with a url' => [['url' => 'https://cdn.example.com/a.jpg', 'path' => 'ignored.jpg'], 'https://cdn.example.com/a.jpg'],
            'array with a path' => [['path' => 'articles/a.jpg'], 'http://localhost/storage/articles/a.jpg'],
            'array with a null url' => [['url' => null, 'path' => '/upload/a.jpg'], 'http://localhost/upload/a.jpg'],
            'array with an empty url' => [['url' => '', 'path' => 'articles/a.jpg'], null],
            'empty array' => [[], null],
            'array of a number' => [['url' => 7], null],
            'protocol-relative' => ['//cdn.example.com/a.jpg', '//cdn.example.com/a.jpg'],
            'spaces around' => ['  articles/a.jpg  ', 'http://localhost/storage/articles/a.jpg'],
            'only spaces' => ['   ', null],
            'number' => [42, null],
        ];
    }

    #[DataProvider('mediaValues')]
    public function test_a_media_value_becomes_an_address(mixed $value, ?string $url): void
    {
        $this->assertSame($url, MediaUrl::to($value));
    }

    public function test_assets_that_were_never_published_say_how_to_publish_them(): void
    {
        $this->withVite();

        try {
            Assets::tags();
            $this->fail('No exception for a missing manifest.');
        } catch (ViteManifestNotFoundException $e) {
            $this->assertStringContainsString('php artisan atrium:install', $e->getMessage());
            $this->assertStringContainsString('--tag=atrium-assets', $e->getMessage());
            $this->assertInstanceOf(ViteManifestNotFoundException::class, $e->getPrevious());
            $this->assertStringContainsString('vendor/atrium/manifest.json', (string) $e->getPrevious()?->getMessage());
        }
    }

    public function test_assets_leave_the_application_vite_as_it_was(): void
    {
        $this->withVite();
        $vite = app(Vite::class);
        $before = (fn (): ?string => $this->buildDirectory)->call($vite);

        try {
            Assets::tags();
        } catch (ViteManifestNotFoundException) {
            // Not published here: only the side effect matters.
        }

        $this->assertSame($before, (fn (): ?string => $this->buildDirectory)->call($vite));
        $this->assertSame('', (string) (function (): string {
            $this->withoutVite();

            return (string) Assets::tags();
        })->call($this));
    }

    private function request(string $uri, string $folder): Request
    {
        return Request::create($uri, 'GET', server: [
            'SCRIPT_NAME' => "/{$folder}/index.php",
            'SCRIPT_FILENAME' => "/var/www/{$folder}/index.php",
            'PHP_SELF' => "/{$folder}/index.php",
        ]);
    }

    public function test_the_root_address_drops_the_folder_of_the_site(): void
    {
        $inside = $this->request('http://example.com/shop2/catalog', 'shop2');
        $this->assertSame('http://example.com/shop2', $inside->root());

        $inside->attributes->set(EnterSiteFolder::FOLDER, 'shop2');
        $this->assertSame('http://example.com', Sites::rootUrl($inside));

        // Another folder, or none named: the root stays as it is.
        $other = $this->request('http://example.com/shop2/catalog', 'shop2');
        $other->attributes->set(EnterSiteFolder::FOLDER, 'shop');
        $this->assertSame('http://example.com/shop2', Sites::rootUrl($other));
        $this->assertSame('http://example.com/shop2', Sites::rootUrl($this->request('http://example.com/shop2/catalog', 'shop2')));
        $this->assertSame('http://example.com', Sites::rootUrl(Request::create('http://example.com/catalog')));
    }

    public function test_before_the_migrations_there_is_one_site_named_after_the_app(): void
    {
        config(['app.name' => 'Garden shop']);

        $this->withoutTables(function (): void {
            $sites = new Sites;

            $this->assertCount(1, $sites->all());
            $this->assertSame('main', $sites->default()->code);
            $this->assertSame('Garden shop', $sites->default()->name);
            $this->assertTrue($sites->default()->active);
            $this->assertTrue($sites->default()->is_default);
            $this->assertFalse($sites->default()->exists);
        });

        // Once migrated, the real sites.
        $this->assertTrue((new Sites)->default()->exists);
    }

    public function test_before_the_migrations_the_content_language_is_the_app_locale(): void
    {
        config(['app.locale' => 'pt_BR']);

        $this->withoutTables(function (): void {
            $languages = new ContentLanguages;

            $this->assertSame(['pt-br'], $languages->codes());
            $this->assertSame('PT-BR', $languages->find('pt-br')?->name);
            $this->assertTrue($languages->find('pt-br')?->is_default);
            $this->assertFalse($languages->isMultilingual());
        });
    }

    public function test_a_site_whose_languages_are_all_switched_off_falls_back_to_the_app_locale(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'active' => false]);
        $site = Site::query()->create(['code' => 'de', 'name' => 'DE', 'folder' => 'de', 'languages' => ['de']]);
        app(Sites::class)->setCurrent($site);

        $this->assertSame(['en'], app(ContentLanguages::class)->codes());
        $this->assertSame('EN', app(ContentLanguages::class)->find('en')?->name);
    }

    public function test_before_the_migrations_no_module_setting_is_stored(): void
    {
        $this->withoutTables(function (): void {
            $this->assertSame([], app(Settings::class)->stored('labels'));
        });

        DB::table(Settings::TABLE)->insert(['module' => 'labels', 'site_id' => null, 'values' => json_encode(['color' => 'red'])]);
        // Remembered for the request; a fresh one reads the row.
        $this->assertSame([], app(Settings::class)->stored('labels'));
        $this->app->forgetInstance(Settings::class);
        $this->assertSame(['color' => 'red'], app(Settings::class)->stored('labels'));
    }

    public function test_more_records_join_the_sitemap_in_every_language_they_have_a_page_in(): void
    {
        $this->travelTo('2026-08-03 10:00:00');
        $shown = Note::query()->create(['name' => 'Shown', 'code' => 'shown']);
        Note::query()->create(['name' => 'Hidden', 'code' => '']);
        $this->travelBack();
        $sitemap = app(Sitemap::class);

        $sitemap->extend(
            static fn (): iterable => Note::query()->orderBy('id')->get(),
            static fn (Model $note, string $language): ?string => $note->getAttribute('code') === '' ? null : url("/{$language}/notes/".$note->getAttribute('code')),
        );

        $entries = $sitemap->entries();

        $this->assertSame(['loc' => 'http://localhost', 'lastmod' => null, 'alternates' => []], $entries[0]);
        $this->assertSame([
            'loc' => 'http://localhost/en/notes/shown',
            'lastmod' => $shown->updated_at?->format(DateTimeInterface::ATOM),
            'alternates' => [],
        ], $entries[1]);
        $this->assertCount(2, $entries);
    }

    public function test_a_demo_seeder_copies_the_files_of_a_folder_to_the_uploads_disk(): void
    {
        Storage::fake('uploads');
        config(['atrium.uploads.disk' => 'uploads']);
        $folder = sys_get_temp_dir().'/atrium-demo-'.bin2hex(random_bytes(4));
        mkdir($folder.'/nested', 0777, true);
        file_put_contents($folder.'/chair.jpg', 'chair');
        file_put_contents($folder.'/manual.pdf', 'manual');
        file_put_contents($folder.'/nested/skip.jpg', 'skip');

        try {
            $seeder = new class extends DemoSeeder
            {
                public string $folder = '';

                public function run(): bool
                {
                    $this->copyFiles($this->folder.'/');

                    return true;
                }

                public function pathOf(string $file): string
                {
                    return $this->path($file);
                }
            };
            $seeder->folder = $folder;

            $this->assertTrue($seeder->run());
        } finally {
            unlink($folder.'/nested/skip.jpg');
            rmdir($folder.'/nested');
            unlink($folder.'/chair.jpg');
            unlink($folder.'/manual.pdf');
            rmdir($folder);
        }

        $this->assertSame('demo/chair.jpg', $seeder->pathOf('chair.jpg'));
        $this->assertSame('chair', Storage::disk('uploads')->get('demo/chair.jpg'));
        $this->assertSame('manual', Storage::disk('uploads')->get('demo/manual.pdf'));
        // Only the files of the folder itself, not of the folders inside.
        $this->assertSame(['demo/chair.jpg', 'demo/manual.pdf'], Storage::disk('uploads')->allFiles());
    }

    public function test_a_demo_seeder_of_a_missing_folder_copies_nothing(): void
    {
        Storage::fake('public');

        $seeder = new class extends DemoSeeder
        {
            public function run(): bool
            {
                $this->copyFiles('/no/such/folder');

                return false;
            }
        };

        $this->assertFalse($seeder->run());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_module_settings_have_no_description_unless_they_give_one(): void
    {
        $settings = new class extends ModuleSettings
        {
            public function fields(): array
            {
                return [];
            }
        };

        $this->assertSame('', $settings->description());
        $this->assertFalse($settings->perSite);
    }

    public function test_a_record_belongs_to_the_site_it_was_made_on(): void
    {
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'folder' => 'shop']);
        app(Sites::class)->setCurrent($site);

        $note = Note::query()->create(['name' => 'Chair', 'code' => 'chair']);
        $elsewhere = Note::query()->create(['name' => 'Table', 'code' => 'table', 'site_id' => app(Sites::class)->default()->id]);

        $this->assertTrue($note->site?->is($site));
        $this->assertSame(app(Sites::class)->default()->id, $elsewhere->site_id);
        $this->assertFalse($elsewhere->site?->is($site));
    }
}

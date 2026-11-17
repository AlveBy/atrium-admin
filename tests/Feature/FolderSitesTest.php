<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

/**
 * Several sites on one domain, each in its own folder: example.com/site2/,
 * like a site's DIR in Bitrix.
 */
final class FolderSitesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
        $app['config']->set('atrium.resources', [Post::class, Note::class]);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            Route::localized(function (): void {
                Route::get('/', fn () => 'home of '.app(Sites::class)->current()->code)->name('home');
                Route::get('notes/{code}', fn (string $code) => app(Sites::class)->current()->code.':'.app()->getLocale().':'.Note::query()->where('code', $code)->firstOrFail()->name)
                    ->name('notes.show');
                Route::get('links', fn () => implode(' ', [
                    url('about'),
                    route('notes.show', ['code' => 'a']),
                    app(ContentLanguages::class)->route('notes.show', ['code' => 'a'], 'de'),
                    asset('build/app.css'),
                    request()->path(),
                ]));
            });

            Route::middleware('slug.redirect')->get('old/{any}', fn () => abort(404))->where('any', '.*');
        });
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function site(string $code, array $attributes = []): Site
    {
        return Site::query()->create(['code' => $code, 'name' => ucfirst($code), ...$attributes]);
    }

    public function test_a_site_is_found_by_domain_and_then_by_folder(): void
    {
        $this->main()->update(['domains' => ['example.com']]);
        $second = $this->site('second', ['domains' => ['example.com'], 'folder' => 'site2']);
        Note::query()->create(['name' => 'Root note', 'code' => 'hello', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'Folder note', 'code' => 'hello', 'site_id' => $second->id]);

        $this->get('http://example.com/site2/notes/hello')->assertOk()->assertSee('second:en:Folder note');
        $this->get('http://example.com/site2')->assertOk()->assertSee('home of second');
        $this->get('http://example.com/site2/')->assertOk()->assertSee('home of second');
        $this->get('http://example.com/notes/hello')->assertOk()->assertSee('main:en:Root note');
        $this->get('http://example.com/')->assertOk()->assertSee('home of main');
    }

    public function test_a_folder_is_a_whole_path_segment(): void
    {
        $this->site('second', ['folder' => 'site2']);

        $this->get('http://main.test/site22')->assertNotFound();
        $this->get('http://main.test/site2x/notes/hello')->assertNotFound();
        $this->assertSame('main', app(Sites::class)->forRequest('main.test', '/site22/notes')->code);
        $this->assertSame('second', app(Sites::class)->forRequest('main.test', '/site2')->code);
        $this->assertSame('second', app(Sites::class)->forRequest('main.test', '/site2/notes')->code);
    }

    public function test_the_longest_folder_wins(): void
    {
        $shop = $this->site('shop', ['folder' => 'shop']);
        $b2b = $this->site('b2b', ['folder' => 'shop/b2b']);
        Note::query()->create(['name' => 'Retail', 'code' => 'price', 'site_id' => $shop->id]);
        Note::query()->create(['name' => 'Wholesale', 'code' => 'price', 'site_id' => $b2b->id]);

        $this->get('http://main.test/shop/b2b/notes/price')->assertOk()->assertSee('b2b:en:Wholesale');
        $this->get('http://main.test/shop/notes/price')->assertOk()->assertSee('shop:en:Retail');
        $this->get('http://main.test/shop/b2bx/notes/price')->assertNotFound();
    }

    public function test_the_content_language_goes_after_the_folder(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $second = $this->site('second', ['folder' => 'site2', 'languages' => ['en', 'de']]);
        Note::query()->create(['name' => 'Hello', 'code' => 'hello', 'site_id' => $second->id]);

        $this->get('http://main.test/site2/de/notes/hello')->assertOk()->assertSee('second:de:Hello');
        $this->get('http://main.test/site2/notes/hello')->assertOk()->assertSee('second:en:Hello');
        // The language is not a folder of its own.
        $this->get('http://main.test/de/site2/notes/hello')->assertNotFound();
    }

    public function test_the_root_site_of_the_domain_or_the_default_site_takes_the_rest(): void
    {
        $shop = $this->site('shop', ['domains' => ['shop.example.com']]);
        $this->site('blog', ['domains' => ['shop.example.com'], 'folder' => 'blog']);
        $this->site('docs', ['domains' => ['docs.example.com'], 'folder' => 'v1']);

        $this->get('http://shop.example.com/')->assertSee('home of shop');
        $this->get('http://shop.example.com/blog')->assertSee('home of blog');
        $this->assertSame($shop->id, app(Sites::class)->forRequest('shop.example.com', '/news')->id);

        // A domain with only a site in a folder: the default site has the rest…
        $this->get('http://docs.example.com/v1')->assertSee('home of docs');
        $this->get('http://docs.example.com/')->assertSee('home of main');
        // …and a folder belongs to its own domains only.
        $this->get('http://main.test/v1')->assertNotFound();
        $this->get('http://unknown.test/blog')->assertNotFound();
    }

    public function test_a_site_without_domains_sits_in_its_folder_on_every_domain(): void
    {
        $this->site('anywhere', ['folder' => 'site2']);
        $this->site('own', ['domains' => ['example.com'], 'folder' => 'site2']);

        $this->get('http://main.test/site2')->assertSee('home of anywhere');
        $this->get('http://unknown.test/site2')->assertSee('home of anywhere');
        // A site that names the domain wins at the same folder.
        $this->get('http://example.com/site2')->assertSee('home of own');
    }

    public function test_an_inactive_folder_site_is_not_served(): void
    {
        $this->site('second', ['folder' => 'site2', 'active' => false]);

        $this->get('http://main.test/site2')->assertNotFound();
    }

    public function test_every_address_built_on_a_folder_site_is_inside_its_folder(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->site('second', ['domains' => ['example.com'], 'folder' => 'site2', 'languages' => ['en', 'de']]);

        $this->get('http://example.com/site2/links')->assertOk()->assertSeeText(implode(' ', [
            'http://example.com/site2/about',
            'http://example.com/site2/notes/a',
            'http://example.com/site2/de/notes/a',
            // Assets are served by the web server from the root.
            'http://example.com/build/app.css',
            'links',
        ]));

        // Nothing carries over to the next request.
        $this->get('http://example.com/links')->assertOk()->assertSeeText('http://example.com/about http://example.com/notes/a http://example.com/de/notes/a http://example.com/build/app.css links');
    }

    public function test_an_old_slug_redirects_inside_the_folder(): void
    {
        $second = $this->site('second', ['folder' => 'site2']);
        $note = Note::query()->create(['name' => 'Hello', 'code' => 'hello', 'site_id' => $second->id]);
        SlugHistory::query()->create(['sluggable_type' => Note::class, 'sluggable_id' => $note->id, 'slug' => 'old-hello', 'full_path' => 'old/old-hello']);

        $this->get('http://main.test/site2/old/old-hello')->assertStatus(301)->assertRedirect('http://main.test/site2/hello');
    }

    public function test_the_admin_and_the_api_are_not_inside_a_folder(): void
    {
        $this->site('second', ['folder' => 'site2']);

        $this->get('http://main.test/site2/admin/login')->assertNotFound();
        $this->get('http://main.test/site2/api/v1/posts')->assertNotFound();
        $this->postJson('http://main.test/site2/mcp', [])->assertNotFound();
        $this->get('http://main.test/admin/login')->assertOk();
    }

    public function test_a_folder_site_has_its_own_sitemap_and_the_domain_one_robots_txt(): void
    {
        $this->app['env'] = 'production';
        $this->site('second', ['folder' => 'site2']);
        $this->site('third', ['domains' => ['main.test'], 'folder' => 'en/shop']);
        $this->site('elsewhere', ['domains' => ['example.com'], 'folder' => 'other']);

        $this->assertSame(
            "User-agent: *\nDisallow:\n\nSitemap: http://main.test/sitemap.xml\nSitemap: http://main.test/en/shop/sitemap.xml\nSitemap: http://main.test/site2/sitemap.xml\n",
            $this->get('http://main.test/robots.txt')->assertOk()->getContent(),
        );

        // robots.txt is read at the root of a domain only.
        $this->get('http://main.test/site2/robots.txt')->assertNotFound();

        $this->get('http://main.test/site2/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://main.test/site2</loc>', false);
    }

    public function test_the_folder_is_normalized_and_checked(): void
    {
        $site = $this->site('second', ['folder' => ' https://example.com//Site2/sub/?x=1 ']);
        $this->assertSame('Site2/sub', $site->folder);

        $site->update(['folder' => '/']);
        $this->assertNull($site->fresh()->folder);

        foreach (['bad folder' => 'folder_invalid', 'admin/x' => 'folder_reserved', 'api' => 'folder_reserved', 'mcp' => 'folder_reserved'] as $folder => $error) {
            try {
                $site->update(['folder' => $folder]);
                $this->fail("The folder [{$folder}] was accepted.");
            } catch (ValidationException $e) {
                $this->assertSame([__('atrium::sites.errors.'.$error, ['folder' => $folder === 'bad folder' ? $folder : strtok($folder, '/')])], $e->errors()['folder']);
            }
        }

        // The default site answers every address nobody claims: at the root.
        try {
            $this->main()->update(['folder' => 'main']);
            $this->fail('The default site was put into a folder.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('folder', $e->errors());
        }
    }

    public function test_a_domain_and_folder_belong_to_one_site(): void
    {
        $this->main()->update(['domains' => ['example.com']]);

        // Another folder of the same domain, or the same folder of another domain: fine.
        $this->site('a', ['domains' => ['example.com'], 'folder' => 'a']);
        $this->site('b', ['domains' => ['example.org'], 'folder' => 'a']);
        $this->site('c', ['folder' => 'c']);

        foreach ([
            ['domains' => ['example.com'], 'folder' => 'a'],
            ['domains' => ['example.org', 'example.net'], 'folder' => '/a/'],
            ['folder' => 'c'],
        ] as $attributes) {
            try {
                $this->site('clash', $attributes);
                $this->fail('A taken address was accepted: '.json_encode($attributes));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('folder', $e->errors());
            }
        }

        // The same domain at the root is still taken.
        $this->expectException(ValidationException::class);
        $this->site('root', ['domains' => ['example.com']]);
    }

    public function test_the_folder_is_set_in_the_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/sites/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fields', fn ($fields) => collect($fields)->contains('name', 'folder')));

        $this->post('/admin/sites', ['name' => 'Second', 'code' => 'second', 'domains' => [], 'folder' => '/site2/', 'active' => true, 'sort' => 200])
            ->assertSessionHasNoErrors();

        $site = Site::query()->where('code', 'second')->sole();
        $this->assertSame('site2', $site->folder);

        $this->post('/admin/sites', ['name' => 'Clash', 'code' => 'clash', 'domains' => [], 'folder' => 'site2', 'active' => true, 'sort' => 300])
            ->assertSessionHasErrors('folder');

        // "Open site" in the header leads into the folder.
        $this->post('http://main.test/admin/site', ['site_id' => $site->id]);
        $this->get('http://main.test/admin')->assertInertia(fn (AssertableInertia $page) => $page->where('currentSite.url', 'http://main.test/site2'));
        $this->assertSame('http://example.com/site2/news', (new Site(['domains' => ['example.com'], 'folder' => 'site2']))->url('news'));
    }
}

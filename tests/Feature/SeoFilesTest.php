<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Story;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use SimpleXMLElement;

final class SeoFilesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
        $app['config']->set('atrium.resources', [Post::class, Story::class]);
    }

    protected function disableRobots($app): void
    {
        $app['config']->set('atrium.robots', false);
    }

    protected function defineRoutes($router): void
    {
        Route::localized(function (): void {
            Route::get('stories/{code}', fn (string $code) => $code)->name('stories.show');
        });
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    private function french(): Site
    {
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);

        return Site::query()->create(['code' => 'fr', 'name' => 'France', 'domains' => ['example.fr'], 'languages' => ['fr']]);
    }

    /**
     * @return array<int,string>
     */
    private function locs(string $xml): array
    {
        $doc = new SimpleXMLElement($xml);

        return array_map('strval', $doc->xpath('//*[local-name()="loc"]') ?: []);
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_the_sitemap_lists_the_sites_own_public_records_in_every_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $fr = $this->french();
        $main = $this->main();

        Story::query()->create(['site_id' => $main->id, 'name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']]);
        Story::query()->create(['site_id' => $main->id, 'name' => ['en' => 'Only English'], 'code' => ['en' => 'only-english']]);
        Story::query()->create(['site_id' => $main->id, 'name' => ['en' => 'Draft'], 'code' => ['en' => 'draft'], 'active' => false]);
        Story::query()->create(['site_id' => $fr->id, 'name' => ['fr' => 'Bonjour'], 'code' => ['fr' => 'bonjour']]);

        $response = $this->get('http://main.test/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));

        $this->assertSame([
            'http://main.test',
            'http://main.test/stories/hello',
            'http://main.test/de/stories/hallo',
            'http://main.test/stories/only-english',
        ], $this->locs($response->getContent()));

        // Each language version names the others, and the default one for the rest of the world.
        $response->assertSee('<xhtml:link rel="alternate" hreflang="de" href="http://main.test/de/stories/hallo"/>', false);
        $response->assertSee('<xhtml:link rel="alternate" hreflang="x-default" href="http://main.test/stories/hello"/>', false);
        $this->assertSame(6, substr_count($response->getContent(), '<xhtml:link'));

        $this->assertSame(
            ['http://example.fr', 'http://example.fr/stories/bonjour'],
            $this->locs($this->get('http://example.fr/sitemap.xml')->assertOk()->getContent()),
        );
    }

    public function test_the_sitemap_is_built_anew_when_a_record_changes(): void
    {
        $story = Story::query()->create(['name' => ['en' => 'Hello'], 'code' => ['en' => 'hello']]);

        $this->get('http://main.test/sitemap.xml')->assertSee('/stories/hello');

        $story->update(['code' => ['en' => 'hello-again']]);
        $this->get('http://main.test/sitemap.xml')->assertSee('/stories/hello-again');

        $story->delete();
        $this->get('http://main.test/sitemap.xml')->assertDontSee('/stories/hello');
    }

    public function test_a_large_sitemap_becomes_an_index_of_parts(): void
    {
        config(['atrium.sitemap.per_file' => 2]);

        foreach (['a', 'b', 'c'] as $code) {
            Story::query()->create(['name' => ['en' => $code], 'code' => ['en' => $code]]);
        }

        $index = $this->get('http://main.test/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertSame(['http://main.test/sitemap-1.xml', 'http://main.test/sitemap-2.xml'], $this->locs($index));

        $this->assertSame(['http://main.test', 'http://main.test/stories/a'], $this->locs($this->get('http://main.test/sitemap-1.xml')->assertOk()->getContent()));
        $this->assertSame(['http://main.test/stories/b', 'http://main.test/stories/c'], $this->locs($this->get('http://main.test/sitemap-2.xml')->assertOk()->getContent()));
        $this->get('http://main.test/sitemap-3.xml')->assertNotFound();
        $this->get('http://main.test/sitemap-0.xml')->assertNotFound();
    }

    public function test_robots_txt_off_production_turns_every_robot_away(): void
    {
        $this->main()->update(['robots_txt' => "User-agent: *\nDisallow: /search"]);

        $response = $this->get('http://main.test/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_robots_txt_on_production_is_the_sites_own_with_its_sitemap(): void
    {
        $this->production();
        $this->french()->update(['robots_txt' => "User-agent: *\r\nDisallow: /recherche\r\n"]);

        $this->assertSame(
            "User-agent: *\nDisallow:\n\nSitemap: http://main.test/sitemap.xml\n",
            $this->get('http://main.test/robots.txt')->assertOk()->getContent(),
        );

        $this->assertSame(
            "User-agent: *\nDisallow: /recherche\n\nSitemap: http://example.fr/sitemap.xml\n",
            $this->get('http://example.fr/robots.txt')->assertOk()->getContent(),
        );

        // A Sitemap line written by hand is not doubled.
        $this->main()->update(['robots_txt' => "User-agent: *\nDisallow:\nSitemap: https://cdn.example.com/map.xml"]);
        $this->assertSame(1, substr_count($this->get('http://main.test/robots.txt')->getContent(), 'Sitemap:'));
    }

    #[DefineEnvironment('disableRobots')]
    public function test_robots_txt_can_be_left_to_the_application(): void
    {
        $this->get('http://main.test/robots.txt')->assertNotFound();
        $this->get('http://main.test/sitemap.xml')->assertOk();
    }

    public function test_the_robots_tab_warns_when_its_text_will_not_be_served(): void
    {
        $public = sys_get_temp_dir().'/atrium-public-'.uniqid();
        mkdir($public);
        file_put_contents($public.'/robots.txt', "User-agent: *\nDisallow:\n");
        $this->app->usePublicPath($public);

        try {
            $page = json_encode(
                $this->actingAs($this->admin(), 'admin')->get("/admin/sites/{$this->main()->id}/edit")->assertOk()->viewData('page'),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            $this->assertStringContainsString(__('atrium::sites.hints.robots_not_production', ['env' => 'testing']), (string) $page);
            $this->assertStringContainsString(__('atrium::sites.hints.robots_file_exists'), (string) $page);
        } finally {
            unlink($public.'/robots.txt');
            rmdir($public);
        }
    }
}

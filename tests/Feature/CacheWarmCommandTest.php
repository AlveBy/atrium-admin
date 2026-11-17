<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\CacheScreen\Aardvark;
use Atrium\Admin\Tests\Fixtures\CacheScreen\Zebra;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Unwarmable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;

/**
 * atrium:cache:warm opens the pages a visitor would open, per site and per
 * language, so the first real visitor finds the cache full.
 */
class CacheWarmCommandTest extends CacheTestCase
{
    /** @var list<string> every address the site was asked for, in order */
    private array $opened = [];

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->any('{path?}', function (Request $request) {
            $this->opened[] = $request->getUri();

            if (str_contains($request->path(), 'gone')) {
                return response('<html><body>gone</body></html>', 404);
            }

            if (str_contains($request->path(), 'broken')) {
                throw new RuntimeException('the page is broken');
            }

            return response('<html><body>ok</body></html>');
        })->where('path', '.*');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Book::class, Zebra::class]);
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array{0:int,1:string}
     */
    private function warm(array $options = [], int $verbosity = BufferedOutput::VERBOSITY_NORMAL): array
    {
        $output = new BufferedOutput($verbosity);
        $code = Artisan::call('atrium:cache:warm', $options, $output);

        return [$code, $output->fetch()];
    }

    private function site(string $code, array $attributes = []): Site
    {
        $site = Site::query()->create($attributes + ['code' => $code, 'name' => ucfirst($code), 'active' => true]);
        $this->app->make(Sites::class)->flush();

        return $site;
    }

    public function test_it_opens_the_home_page_and_the_pages_of_the_modules_in_every_language(): void
    {
        $this->app->make(RecordCache::class)->warmUsing(fn (string $language): array => ['https://elsewhere.test/shop/'.$language.'?page=1', '/news/'.$language]);

        [$code, $output] = $this->warm();

        $this->assertSame(0, $code);
        $this->assertSame([
            'http://localhost/',
            'http://localhost/shop/en?page=1',
            'http://localhost/news/en',
            'http://localhost/',
            'http://localhost/shop/de?page=1',
            'http://localhost/news/de',
        ], $this->opened);
        $this->assertStringContainsString('Opened 6 pages.', $output);
        $this->assertStringNotContainsString('cards', $output);
    }

    public function test_it_lists_the_pages_only_when_asked_to_be_verbose_or_when_one_fails(): void
    {
        $this->app->make(RecordCache::class)->warmUsing(fn (string $language): array => ['/gone/'.$language]);

        [, $quiet] = $this->warm();
        [, $verbose] = $this->warm([], BufferedOutput::VERBOSITY_VERBOSE);

        $this->assertStringContainsString('  404 http://localhost/gone/en', $quiet);
        $this->assertStringContainsString('  404 http://localhost/gone/de', $quiet);
        $this->assertStringNotContainsString('200', $quiet);
        $this->assertStringContainsString('  200 http://localhost/', $verbose);
        $this->assertStringContainsString('  404 http://localhost/gone/en', $verbose);
    }

    public function test_a_page_that_fails_is_reported_and_the_rest_are_opened(): void
    {
        $this->app->make(RecordCache::class)->warmUsing(fn (string $language): array => ['/broken/'.$language, '/after/'.$language]);

        [$code, $output] = $this->warm();

        $this->assertSame(0, $code);
        $this->assertCount(6, $this->opened);
        $this->assertStringContainsString('  500 http://localhost/broken/en', $output);
        $this->assertStringContainsString('  500 http://localhost/broken/de', $output);
        $this->assertStringContainsString('Opened 6 pages.', $output);
    }

    public function test_a_kernel_that_throws_is_reported_and_counted_as_a_failed_page(): void
    {
        Exceptions::fake();
        $kernel = new class($this->app) implements Kernel
        {
            /** @var list<string> */
            public array $terminated = [];

            public function __construct(private readonly Application $app) {}

            public function bootstrap(): void {}

            public function handle($request): Response
            {
                if (str_contains($request->getPathInfo(), 'handle')) {
                    throw new RuntimeException('handle failed');
                }

                return new Response('ok', str_contains($request->getPathInfo(), 'gone') ? 404 : 200);
            }

            public function terminate($request, $response): void
            {
                $this->terminated[] = $request->getPathInfo();

                if (str_contains($request->getPathInfo(), 'terminate')) {
                    throw new RuntimeException('terminate failed');
                }
            }

            public function getApplication(): Application
            {
                return $this->app;
            }
        };
        $this->app->instance(Kernel::class, $kernel);
        $this->app->make(RecordCache::class)->warmUsing(fn (string $language): array => ['/handle', '/terminate', '/gone']);

        [$code, $output] = $this->warm();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Opened 8 pages.', $output);
        $this->assertStringContainsString('  500 http://localhost/handle', $output);
        $this->assertStringContainsString('  500 http://localhost/terminate', $output);
        $this->assertStringContainsString('  404 http://localhost/gone', $output);
        $this->assertSame(['/', '/terminate', '/gone', '/', '/terminate', '/gone'], $kernel->terminated);
        Exceptions::assertReportedCount(4);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'handle failed');
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'terminate failed');
    }

    public function test_it_does_nothing_when_the_record_cache_is_off(): void
    {
        config(['atrium.cache.enabled' => false, 'session.driver' => 'file']);

        [$code, $output] = $this->warm();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('The record cache is off (ATRIUM_CACHE_ENABLED=false): nothing to warm.', $output);
        $this->assertSame([], $this->opened);
        $this->assertSame('file', config('session.driver'));
    }

    public function test_it_works_with_the_record_cache_on_by_default(): void
    {
        $cache = (array) config('atrium.cache');
        unset($cache['enabled']);
        config(['atrium.cache' => $cache]);

        [, $output] = $this->warm();

        $this->assertStringContainsString('Opened 2 pages.', $output);
    }

    public function test_it_keeps_no_session_per_page(): void
    {
        config(['session.driver' => 'file']);

        $this->warm();

        $this->assertSame('array', config('session.driver'));
    }

    public function test_it_forgets_the_site_it_warmed_last(): void
    {
        $this->warm();

        $sites = $this->app->make(Sites::class);

        $this->assertFalse($sites->isResolved());
    }

    public function test_it_opens_every_active_site_in_the_languages_of_that_site(): void
    {
        Site::query()->delete();
        $this->site('shop', ['domains' => ['shop.test'], 'is_default' => true, 'languages' => ['en']]);
        $this->site('blog', ['domains' => ['blog.test'], 'languages' => ['en', 'de']]);
        $this->site('old', ['domains' => ['old.test'], 'active' => false]);

        [, $output] = $this->warm();

        $this->assertSame(['http://shop.test/', 'http://blog.test/', 'http://blog.test/'], $this->opened);
        $this->assertStringContainsString('Opened 3 pages.', $output);
    }

    public function test_it_opens_only_the_sites_it_is_told_to(): void
    {
        Site::query()->delete();
        $this->site('shop', ['domains' => ['shop.test'], 'is_default' => true, 'languages' => ['en']]);
        $this->site('blog', ['domains' => ['blog.test'], 'languages' => ['en']]);
        $this->site('docs', ['domains' => ['docs.test'], 'languages' => ['en']]);

        [, $output] = $this->warm(['--site' => ['blog']]);

        $this->assertSame(['http://blog.test/'], $this->opened);
        $this->assertStringContainsString('Opened 1 pages.', $output);

        $this->opened = [];
        $this->warm(['--site' => ['docs', 'shop']]);

        $this->assertSame(['http://shop.test/', 'http://docs.test/'], $this->opened);
    }

    public function test_a_site_that_is_told_but_inactive_is_not_opened(): void
    {
        Site::query()->delete();
        $this->site('shop', ['domains' => ['shop.test'], 'is_default' => true]);
        $this->site('old', ['domains' => ['old.test'], 'active' => false]);

        [, $output] = $this->warm(['--site' => ['old']]);

        $this->assertSame([], $this->opened);
        $this->assertStringContainsString('Opened 0 pages.', $output);
    }

    public function test_an_empty_site_option_opens_every_site(): void
    {
        [, $output] = $this->warm(['--site' => ['']]);

        $this->assertStringContainsString('Opened 2 pages.', $output);
    }

    public function test_a_site_that_does_not_exist_opens_nothing(): void
    {
        [, $output] = $this->warm(['--site' => ['ghost']]);

        $this->assertSame([], $this->opened);
        $this->assertStringContainsString('Opened 0 pages.', $output);
    }

    /**
     * @return array<string,array{0:string,1:array<string,mixed>,2:string}>
     */
    public static function whereASiteIs(): array
    {
        return [
            'the domain it lists' => ['https://app.test', ['domains' => ['shop.test']], 'https://shop.test/'],
            'the first of its domains' => ['https://app.test', ['domains' => ['shop.test', 'other.test']], 'https://shop.test/'],
            'the scheme of the app' => ['http://app.test', ['domains' => ['shop.test']], 'http://shop.test/'],
            'an app address with no scheme means http' => ['app.test', ['domains' => ['shop.test']], 'http://shop.test/'],
            'its folder' => ['https://app.test', ['domains' => ['shop.test'], 'folder' => 'b2b'], 'https://shop.test/b2b/'],
            'a folder and a domain with slashes around' => ['https://app.test', ['domains' => ['shop.test/'], 'folder' => '/b2b/'], 'https://shop.test/b2b/'],
            'the app address when it has no domain' => ['https://app.test/', ['domains' => []], 'https://app.test/'],
            'the app address with a folder when it has no domain' => ['https://app.test/', ['domains' => [], 'folder' => 'b2b'], 'https://app.test/b2b/'],
            'the app address when its domain is empty' => ['https://app.test', ['domains' => ['']], 'https://app.test/'],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    #[DataProvider('whereASiteIs')]
    public function test_a_site_is_opened_where_it_lives(string $appUrl, array $attributes, string $expected): void
    {
        config(['app.url' => $appUrl]);
        Site::query()->delete();
        $this->site('main', ['is_default' => true, 'domains' => ['main.test']]);
        $this->site('shop', ['languages' => ['en'], 'domains' => ['shop.test']]);
        // Written past the model, which would refuse some of these.
        DB::table('admin_sites')->where('code', 'shop')->update(['domains' => json_encode($attributes['domains']), 'folder' => $attributes['folder'] ?? null]);
        $this->app->make(Sites::class)->flush();

        $this->warm(['--site' => ['shop']]);

        $this->assertSame([$expected], $this->opened);
    }

    public function test_an_app_address_that_is_not_a_string_is_still_read_as_one(): void
    {
        config(['app.url' => 8080]);
        Site::query()->delete();
        $this->site('main', ['is_default' => true, 'domains' => ['main.test']]);
        $this->app->make(Sites::class)->flush();

        $this->warm();

        $this->assertSame(['http://main.test/', 'http://main.test/'], $this->opened);
    }

    public function test_a_default_site_that_is_not_saved_yet_is_opened_at_the_address_of_the_app(): void
    {
        config(['app.url' => 'https://app.test/']);
        Site::query()->delete();
        $this->app->make(Sites::class)->flush();

        $this->warm();

        $this->assertSame(['https://app.test/', 'https://app.test/'], $this->opened);
    }

    public function test_it_builds_the_cards_of_every_record_when_asked_to(): void
    {
        $this->books(3);
        DB::table('cache_screen_zebras')->insert([['name' => 'One'], ['name' => 'Two']]);
        DB::table('cache_screen_aardvarks')->insert([['name' => 'Single']]);
        // The modules of the panel (books, zebras, posts) and the models the theme gives addresses to.
        config(['atrium.public_urls' => [
            Book::class => [],
            'Not\\A\\Class' => [],
            Post::class => [],
            stdClass::class => [],
            Unwarmable::class => [],
            Aardvark::class => [],
            5 => [],
        ]]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        [$code, $output] = $this->warm(['--records' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Opened 2 pages and built 12 cards.', $output);
        // Only the key of each record is read, 500 at a time.
        $this->assertNotEmpty(preg_grep('/^select\s+\S*cache_books\S*\.\S*id\S*\s+from\s+\S*cache_books\S*\s.*limit 500$/', $queries));
        $this->assertNotEmpty(preg_grep('/^select\s+\S*cache_screen_zebras\S*\.\S*id\S*\s+from\s/', $queries));
        $this->assertNotEmpty(preg_grep('/^select\s+\S*cache_screen_aardvarks\S*\.\S*id\S*\s+from\s/', $queries));
    }

    public function test_the_cards_it_built_are_read_without_a_query(): void
    {
        [$first, $second] = $this->books(2);

        $this->warm(['--records' => true]);
        $this->resetRequest();
        DB::table('cache_books')->delete();
        // A visitor's request is about the site the warmed pages were.
        $this->app->make(Sites::class)->setCurrent(Site::query()->firstOrFail());

        $this->assertSame('Book 1', Book::cached($first->id)?->name);
        $this->assertNotNull(Book::cached($second->id, 'de'));
    }

    public function test_it_builds_the_cards_of_each_site_in_each_of_its_languages(): void
    {
        Site::query()->delete();
        $this->site('shop', ['domains' => ['shop.test'], 'is_default' => true, 'languages' => ['en']]);
        $this->site('blog', ['domains' => ['blog.test'], 'languages' => ['en', 'de']]);
        $this->books(2);

        [, $output] = $this->warm(['--records' => true]);

        $this->assertStringContainsString('Opened 3 pages and built 6 cards.', $output);
    }

    public function test_it_builds_no_cards_unless_asked_to(): void
    {
        $this->books(2);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        [, $output] = $this->warm();

        $this->assertStringNotContainsString('cards', $output);
        $this->assertSame([], preg_grep('/limit 500/', $queries));
    }

    public function test_it_lets_go_of_what_a_chunk_of_cards_read(): void
    {
        $this->books(2);

        $this->warm(['--records' => true]);

        $this->assertSame([], $this->app->make(RecordCache::class)->readNames());
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

/**
 * Anything else worth keeping until a record of some classes changes: what
 * it is kept under, what drops it and what the page that read it learns.
 */
final class RecordCacheRememberTest extends CacheTestCase
{
    private RecordCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->app->make(RecordCache::class);
        $this->app->make(ContentLanguages::class)->default();
        $this->resetRequest();
    }

    /**
     * @return array{0:callable():int,1:callable():int} a callback that counts its runs, and the count
     */
    private function counter(mixed $value = 'value'): array
    {
        $runs = 0;

        return [
            function () use (&$runs, $value): mixed {
                $runs++;

                return $value;
            },
            function () use (&$runs): int {
                return $runs;
            },
        ];
    }

    public function test_a_value_is_made_once_and_read_back_after_that(): void
    {
        [$make, $runs] = $this->counter(['a' => 1]);

        $first = $this->cache->remember(Book::class, 'k', $make);
        $second = $this->cache->remember(Book::class, 'k', $make);

        $this->assertSame(['a' => 1], $first);
        $this->assertSame(['a' => 1], $second);
        $this->assertSame(1, $runs());
        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->requestStats());
    }

    public function test_a_value_that_is_nothing_is_kept_too(): void
    {
        [$make, $runs] = $this->counter(null);

        $this->assertNull($this->cache->remember(Book::class, 'k', $make));
        $this->assertNull($this->cache->remember(Book::class, 'k', $make));

        $this->assertSame(1, $runs());
        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->requestStats());
    }

    public function test_the_same_name_under_another_key_or_class_is_another_value(): void
    {
        [$make, $runs] = $this->counter();

        $this->cache->remember(Book::class, 'a', $make);
        $this->cache->remember(Book::class, 'b', $make);
        $this->cache->remember(Shelf::class, 'a', $make);

        $this->assertSame(3, $runs());
    }

    public function test_something_else_under_the_key_is_not_a_value_of_ours(): void
    {
        [$cache, $repository] = $this->spyCache();
        [$make, $runs] = $this->counter('mine');
        $cache->remember(Book::class, 'k', $make);
        $key = (string) array_key_first($repository->callsTo('putMany')[0][0]);

        foreach ([['no v here' => 1], 'text', 5, null] as $other) {
            $repository->put($key, $other, 60);

            $this->assertSame('mine', $cache->remember(Book::class, 'k', $make));
        }

        $this->assertSame(5, $runs());
    }

    public function test_with_the_cache_off_the_value_is_made_every_time_and_the_store_is_left_alone(): void
    {
        [$cache, $repository] = $this->spyCache();
        config(['atrium.cache.enabled' => false]);
        [$make, $runs] = $this->counter();

        $this->assertSame('value', $cache->remember(Book::class, 'k', $make));
        $this->assertSame('value', $cache->remember(Book::class, 'k', $make));

        $this->assertSame(2, $runs());
        $this->assertSame([], $repository->calls);
        $this->assertSame([], $cache->readNames());
        $this->assertSame(['hits' => 0, 'misses' => 0], $cache->requestStats());
    }

    public function test_a_value_is_kept_under_its_versions_and_the_site_it_is_for(): void
    {
        [$cache, $repository] = $this->spyCache();

        $cache->remember(Book::class, 'k', fn (): string => 'v');

        $stamp = $cache->versions()->stamp(['all', 'list:'.Book::class, 'site:0']);
        $put = $repository->callsTo('putMany');
        $this->assertSame(['atrium:r:'.$stamp.':0:k' => ['v' => 'v']], $put[0][0]);
        $this->assertSame(3600, $put[0][1]);
    }

    public function test_a_value_for_every_site_has_no_site_in_its_key(): void
    {
        [$cache, $repository] = $this->spyCache();

        $cache->remember([Book::class, Shelf::class], 'k', fn (): string => 'v', perSite: false);

        $stamp = $cache->versions()->stamp(['all', 'list:'.Book::class, 'list:'.Shelf::class]);
        $this->assertSame(['atrium:r:'.$stamp.':k' => ['v' => 'v']], $repository->callsTo('putMany')[0][0]);
        $this->assertSame(['all', 'list:'.Book::class, 'list:'.Shelf::class], $cache->readNames());
    }

    public function test_a_value_for_a_site_is_for_that_site_by_default(): void
    {
        [$cache] = $this->spyCache();

        $cache->remember(Book::class, 'k', fn (): string => 'v');

        $this->assertSame(['all', 'list:'.Book::class, 'site:0'], $cache->readNames());
    }

    public function test_a_page_that_reads_a_value_learns_every_class_it_depends_on(): void
    {
        $this->cache->remember([Book::class, Shelf::class], 'k', fn (): string => 'v');

        $this->assertSame(['all', 'list:'.Book::class, 'list:'.Shelf::class, 'site:0'], $this->cache->readNames());
    }

    public function test_a_single_class_may_be_named_alone(): void
    {
        $this->cache->remember(Shelf::class, 'k', fn (): string => 'v');

        $this->assertSame(['all', 'list:'.Shelf::class, 'site:0'], $this->cache->readNames());
    }

    public function test_a_value_of_a_site_is_made_again_for_another_site(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember(Book::class, 'k', $make);
        $shop = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'active' => true]);
        $sites = $this->app->make(Sites::class);
        $sites->flush();
        $sites->setCurrent($shop);
        $this->resetRequest();

        $this->cache->remember(Book::class, 'k', $make);
        $this->cache->remember(Book::class, 'k', $make);

        $this->assertSame(2, $runs());
        $this->assertContains('site:'.$shop->id, $this->cache->readNames());
    }

    public function test_a_value_for_every_site_is_not_made_again_for_another_site(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember(Book::class, 'k', $make, perSite: false);
        $shop = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'active' => true]);
        $sites = $this->app->make(Sites::class);
        $sites->flush();
        $sites->setCurrent($shop);
        $this->resetRequest();

        $this->cache->remember(Book::class, 'k', $make, perSite: false);

        $this->assertSame(1, $runs());
    }

    public function test_a_current_site_that_is_not_saved_yet_is_site_zero(): void
    {
        $sites = $this->app->make(Sites::class);
        $unsaved = new Site;
        (new ReflectionProperty(Sites::class, 'all'))->setValue($sites, new Collection([$unsaved]));
        $sites->setCurrent($unsaved);

        $this->cache->remember(Book::class, 'k', fn (): string => 'v');

        $this->assertContains('site:0', $this->cache->readNames());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:?int,2:int}>
     */
    public static function valueLifetimes(): array
    {
        return [
            'the lists lifetime by default' => [[], null, 3600],
            'what is configured for lists' => [['lists' => 90], null, 90],
            'what the call asks for' => [['lists' => 90], 15, 15],
            'what the call asks for beats the default' => [[], 7200, 7200],
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     */
    #[DataProvider('valueLifetimes')]
    public function test_a_value_lives_as_long_as_it_was_told_or_as_lists_do(array $config, ?int $ttl, int $seconds): void
    {
        config(['atrium.cache.ttl' => $config]);
        [$cache, $repository] = $this->spyCache();

        $cache->remember(Book::class, 'k', fn (): string => 'v', $ttl);

        $this->assertSame($seconds, $repository->callsTo('putMany')[0][1]);
    }

    public function test_a_value_is_made_again_when_a_class_it_depends_on_changes_and_not_otherwise(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember([Book::class, Shelf::class], 'k', $make);
        [$book] = $this->books(1);
        $this->resetRequest();
        $this->assertSame(2, $this->remembered($make, $runs, [Book::class, Shelf::class]), 'a book was saved');

        $tag = new Tag(['name' => 'green']);
        $tag->save();
        $this->resetRequest();

        $this->assertSame(2, $this->remembered($make, $runs, [Book::class, Shelf::class]), 'a tag is not a dependency');

        $book->save();
        $this->cache->changed($book);
        $this->resetRequest();

        $this->assertSame(3, $this->remembered($make, $runs, [Book::class, Shelf::class]));
    }

    /**
     * @param  callable():mixed  $make
     * @param  callable():int  $runs
     * @param  array<int,class-string>  $classes
     */
    private function remembered(callable $make, callable $runs, array $classes): int
    {
        $this->cache->remember($classes, 'k', $make(...));

        return $runs();
    }

    public function test_flushing_a_name_of_ones_own_makes_its_values_again(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember('my-block', 'k', $make);
        $this->cache->remember('my-block', 'k', $make);
        $this->assertSame(1, $runs());

        $this->cache->flushClass('my-block');
        $this->resetRequest();
        $this->cache->remember('my-block', 'k', $make);

        $this->assertSame(2, $runs());
    }

    public function test_flushing_everything_makes_values_again(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember(Book::class, 'k', $make);

        $this->cache->flushAll();
        $this->resetRequest();
        $this->cache->remember(Book::class, 'k', $make);

        $this->assertSame(2, $runs());
    }

    public function test_flushing_one_site_makes_only_its_values_again(): void
    {
        [$make, $runs] = $this->counter();
        $this->cache->remember(Book::class, 'k', $make);
        $this->cache->remember(Book::class, 'k', $make, perSite: false);

        $this->cache->flushSite(0);
        $this->resetRequest();
        $this->cache->remember(Book::class, 'k', $make);
        $this->cache->remember(Book::class, 'k', $make, perSite: false);

        $this->assertSame(3, $runs());
    }

    public function test_the_entries_of_a_value_go_in_the_group_of_the_first_class(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->remember([Book::class, Shelf::class], 'k', fn (): string => 'v');

        $group = 'atrium:'.str_replace('\\', '.', Book::class);
        $this->assertContains([['atrium', $group]], $repository->callsTo('tags'));
        $this->assertNotContains([['atrium', 'atrium:'.str_replace('\\', '.', Shelf::class)]], $repository->callsTo('tags'));
    }

    public function test_the_entries_of_a_value_with_no_class_go_in_the_group_called_remember(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->remember([], 'k', fn (): string => 'v');

        $this->assertContains([['atrium', 'atrium:remember']], $repository->callsTo('tags'));
        $this->assertSame(['all', 'site:0'], $cache->readNames());
    }
}

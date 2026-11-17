<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\SpyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Stringable;

/**
 * What the record cache keeps for one request: its counts, the names it
 * read, the time it shows, the pages to warm, and the modules that add to
 * its cards.
 */
final class RecordCacheRequestTest extends CacheTestCase
{
    private RecordCache $cache;

    private SpyRepository $store;

    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->cache, $this->store] = $this->spyCache();
        $this->timezone = date_default_timezone_get();
        $this->app->make(ContentLanguages::class)->default();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->timezone);

        parent::tearDown();
    }

    /**
     * One miss and one hit of a value kept.
     */
    private function missAndHit(RecordCache $cache): void
    {
        $cache->remember(Book::class, 'k', fn (): int => 1);
        $cache->remember(Book::class, 'k', fn (): int => 1);
    }

    public function test_the_cache_hands_out_the_store_and_the_versions_it_was_made_with(): void
    {
        $repository = new SpyRepository;
        $store = new CacheStore($repository, false);
        $versions = new Versions($repository);

        $cache = new RecordCache($store, $versions);

        $this->assertSame($store, $cache->store());
        $this->assertSame($versions, $cache->versions());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:bool}>
     */
    public static function enabledSettings(): array
    {
        return [
            'on when nothing is said' => [[], true],
            'on' => [['enabled' => true], true],
            'off' => [['enabled' => false], false],
            'off as zero' => [['enabled' => 0], false],
            'off as text zero' => [['enabled' => '0'], false],
            'on as one' => [['enabled' => 1], true],
        ];
    }

    /**
     * @param  array<string,mixed>  $setting
     */
    #[DataProvider('enabledSettings')]
    public function test_the_cache_is_on_unless_the_configuration_turns_it_off(array $setting, bool $enabled): void
    {
        config(['atrium.cache' => $setting]);

        $this->assertSame($enabled, $this->cache->enabled());
    }

    public function test_a_group_is_a_class_name_with_dots_for_backslashes(): void
    {
        $this->assertSame('Atrium.Admin.Models.Site', RecordCache::group('Atrium\\Admin\\Models\\Site'));
        $this->assertSame('my-block', RecordCache::group('my-block'));
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:int}> the setting, the timestamp the time comes down to
     */
    public static function timeSteps(): array
    {
        return [
            'a minute when nothing is said' => [[], 1800000120],
            'a minute' => [['time_step' => 60], 1800000120],
            'a hundred seconds' => [['time_step' => 100], 1800000100],
            'twenty-five seconds' => [['time_step' => 25], 1800000125],
            'a step from the environment' => [['time_step' => '45'], 1800000135],
            'a second' => [['time_step' => 1], 1800000137],
            'none is a second' => [['time_step' => 0], 1800000137],
            'less than none is a second' => [['time_step' => -5], 1800000137],
        ];
    }

    /**
     * @param  array<string,mixed>  $setting
     */
    #[DataProvider('timeSteps')]
    public function test_the_time_of_lists_moves_in_steps(array $setting, int $expected): void
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1800000137));
        config(['atrium.cache' => $setting]);

        $this->assertSame($expected, $this->cache->now()->getTimestamp());
    }

    public function test_the_time_of_lists_is_in_the_time_zone_of_the_application(): void
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(1800000137));
        date_default_timezone_set('Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', $this->cache->now()->getTimezone()->getName());

        date_default_timezone_set('America/New_York');

        $this->assertSame('America/New_York', $this->cache->now()->getTimezone()->getName());
    }

    public function test_a_request_counts_its_hits_and_misses(): void
    {
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->requestStats());

        $this->missAndHit($this->cache);

        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->requestStats());
        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->finishedStats());
    }

    /**
     * @return array<string,array{0:int,1:int}> misses, hits
     */
    public static function countsOfARequest(): array
    {
        return [
            'only misses' => [3, 0],
            'only hits' => [0, 3],
            'both' => [2, 4],
        ];
    }

    #[DataProvider('countsOfARequest')]
    public function test_the_counts_of_a_request_that_did_anything_are_its_own_whichever_they_are(int $misses, int $hits): void
    {
        for ($i = 0; $i < $misses; $i++) {
            $this->cache->remember(Book::class, 'k'.$i, fn (): int => 1);
        }

        for ($i = 0; $i < $hits; $i++) {
            $this->cache->remember(Book::class, 'k0', fn (): int => 1);
        }

        $own = $this->cache->requestStats();
        $this->cache->reset();
        // A request that did nothing since reset: the counts it closed with.
        $this->assertSame($own, $this->cache->finishedStats());
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->requestStats());
    }

    public function test_the_counts_of_a_request_that_did_something_after_a_reset_replace_the_closed_ones(): void
    {
        $this->missAndHit($this->cache);
        $this->cache->reset();
        $this->cache->remember(Book::class, 'other', fn (): int => 1);

        $this->assertSame(['hits' => 0, 'misses' => 1], $this->cache->finishedStats());
    }

    public function test_a_request_that_counted_nothing_leaves_the_counts_that_were_closed_before(): void
    {
        $this->missAndHit($this->cache);
        $this->cache->reset();
        $this->cache->reset();

        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->finishedStats());
    }

    public function test_a_cache_that_never_counted_anything_finishes_with_zeros(): void
    {
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->finishedStats());
    }

    public function test_a_request_starts_afresh_after_a_reset(): void
    {
        $this->cache->remember(Book::class, 'k', fn (): int => 1);
        $this->cache->dontKeepPage();
        $this->assertNotSame([], $this->cache->readNames());
        $this->assertFalse($this->cache->pageKeepable());

        $this->cache->reset();

        $this->assertSame([], $this->cache->readNames());
        $this->assertTrue($this->cache->pageKeepable());
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->requestStats());
    }

    public function test_a_reset_makes_the_next_read_see_the_versions_another_process_bumped(): void
    {
        $name = 'list:'.Book::class;
        $before = $this->cache->versions()->get([$name])[$name];
        $this->store->put('atrium:v:'.$name, 123456, 60);

        $this->assertSame($before, $this->cache->versions()->get([$name])[$name]);

        $this->cache->reset();

        $this->assertSame('123456', $this->cache->versions()->get([$name])[$name]);
    }

    public function test_a_reset_makes_the_store_work_out_its_tagged_keys_again(): void
    {
        [$cache, $repository] = $this->spyCache(true);
        $cache->store()->many('group', ['a']);
        $cache->store()->many('group', ['b']);
        $before = count($repository->callsTo('tags'));

        $cache->reset();
        $cache->store()->many('group', ['a']);

        $this->assertSame($before + 1, count($repository->callsTo('tags')));
    }

    public function test_a_page_is_kept_unless_something_made_for_one_visitor_was_on_it(): void
    {
        $this->assertTrue($this->cache->pageKeepable());

        $this->cache->markRead(['all', 'list:'.Book::class]);
        $this->assertTrue($this->cache->pageKeepable());

        $this->cache->dontKeepPage();
        $this->assertFalse($this->cache->pageKeepable());
        $this->assertContains(RecordCache::NO_PAGE, $this->cache->readNames());
    }

    public function test_the_names_a_request_read_are_listed_once_each_in_the_order_they_came(): void
    {
        $this->cache->markRead(['b', 'a']);
        $this->cache->markRead(['a', 'c']);

        $this->assertSame(['b', 'a', 'c'], $this->cache->readNames());
    }

    public function test_the_numbers_of_a_request_are_added_to_the_totals(): void
    {
        $this->missAndHit($this->cache);
        $this->cache->persistStats();

        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->totals());

        $this->cache->reset();
        $this->cache->remember(Book::class, 'other', fn (): int => 1);
        $this->cache->persistStats();

        $this->assertSame(['hits' => 1, 'misses' => 2], $this->cache->totals());
    }

    public function test_a_count_of_zero_is_not_written_down(): void
    {
        $this->cache->remember(Book::class, 'k', fn (): int => 1);

        $this->cache->persistStats();

        $this->assertSame([['atrium:stats:misses', 1]], array_values(array_filter($this->store->callsTo('increment'), static fn (array $call): bool => str_starts_with((string) $call[0], 'atrium:stats:'))));
        $this->assertSame(['hits' => 0, 'misses' => 1], $this->cache->totals());
    }

    public function test_a_store_that_cannot_increment_a_missing_total_starts_it(): void
    {
        $this->store->memcached = true;
        $this->missAndHit($this->cache);
        $this->store->calls = [];

        $this->cache->persistStats();

        $this->assertSame([['atrium:stats:hits', 1], ['atrium:stats:misses', 1]], $this->store->callsTo('increment'));
        $this->assertSame([['atrium:stats:hits', 1], ['atrium:stats:misses', 1]], $this->store->callsTo('forever'));
        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->totals());
    }

    public function test_a_store_that_increments_does_not_start_the_total_again(): void
    {
        $this->missAndHit($this->cache);
        $this->store->calls = [];

        $this->cache->persistStats();

        $this->assertSame([], $this->store->callsTo('forever'));
    }

    public function test_with_the_counts_turned_off_nothing_is_written(): void
    {
        config(['atrium.cache.stats' => false]);
        $this->missAndHit($this->cache);
        $this->store->calls = [];

        $this->cache->persistStats();

        $this->assertSame([], $this->store->calls);
    }

    public function test_a_cache_that_is_down_costs_the_request_no_error_for_its_counts(): void
    {
        Exceptions::fake();
        $this->missAndHit($this->cache);
        $this->store->failing = 'increment';

        $this->cache->persistStats();

        Exceptions::assertNothingReported();
        $this->assertSame(1, count($this->store->callsTo('increment')));
    }

    public function test_a_request_that_did_nothing_writes_nothing(): void
    {
        $this->cache->persistStats();

        $this->assertSame([], $this->store->callsTo('increment'));
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->totals());
    }

    public function test_the_totals_are_numbers_whatever_the_store_kept(): void
    {
        $this->store->put('atrium:stats:hits', '5', 60);
        $this->store->put('atrium:stats:misses', '7.0', 60);

        $this->assertSame(['hits' => 5, 'misses' => 7], $this->cache->totals());
    }

    public function test_the_totals_start_at_zero(): void
    {
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->totals());
    }

    public function test_clearing_the_totals_clears_both(): void
    {
        $this->missAndHit($this->cache);
        $this->cache->persistStats();

        $this->cache->resetTotals();

        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->totals());
        $this->assertSame([['atrium:stats:hits'], ['atrium:stats:misses']], $this->store->callsTo('forget'));
    }

    public function test_the_pages_to_warm_start_with_the_home_page(): void
    {
        $this->assertSame(['/'], $this->cache->warmPaths('en'));
    }

    public function test_the_pages_to_warm_are_made_of_the_paths_and_queries_of_what_modules_add(): void
    {
        $this->cache->warmUsing(static fn (): array => [
            'https://example.com/books?page=2',
            '/news/',
            'about',
            'https://example.com',
            'https://example.com//double',
            '/empty?',
            '/books?page=2',
            '/',
        ]);

        $this->assertSame(['/', '/books?page=2', '/news/', '/about', '/double', '/empty'], $this->cache->warmPaths('en'));
    }

    public function test_the_pages_to_warm_may_be_given_as_anything_that_prints_as_an_address(): void
    {
        $this->cache->warmUsing(static fn (): array => [new class implements Stringable
        {
            public function __toString(): string
            {
                return 'https://example.com/shop?tab=1';
            }
        }]);

        $this->assertSame(['/', '/shop?tab=1'], $this->cache->warmPaths('en'));
    }

    public function test_the_pages_to_warm_may_come_from_a_generator(): void
    {
        $this->cache->warmUsing(static function (): iterable {
            yield '/a';
            yield '/b';
        });

        $this->assertSame(['/', '/a', '/b'], $this->cache->warmPaths('en'));
    }

    public function test_every_module_is_asked_for_the_pages_in_the_language_and_in_the_order_it_came(): void
    {
        $asked = [];
        $this->cache->warmUsing(static function (string $language) use (&$asked): array {
            $asked[] = 'first:'.$language;

            return ['/one'];
        });
        $this->cache->warmUsing(static function (string $language) use (&$asked): array {
            $asked[] = 'second:'.$language;

            return ['/two'];
        });

        $this->assertSame(['/', '/one', '/two'], $this->cache->warmPaths('de'));
        $this->assertSame(['first:de', 'second:de'], $asked);
    }

    private function cardsWithNoCache(): RecordCache
    {
        config(['atrium.cache.enabled' => false]);

        return $this->cache;
    }

    public function test_the_builder_is_made_once_and_made_again_when_a_module_adds_to_the_cards(): void
    {
        $first = $this->cache->builder();

        $this->assertSame($first, $this->cache->builder());

        $this->cache->extend('stars', static fn (): array => []);

        $second = $this->cache->builder();
        $this->assertNotSame($first, $second);
        $this->assertSame($second, $this->cache->builder());

        $this->cache->thumbnailsUsing(null);

        $this->assertNotSame($second, $this->cache->builder());
    }

    public function test_what_a_module_adds_shows_in_the_cards_built_after_it_was_added(): void
    {
        [$book] = $this->books(1);
        $cache = $this->cardsWithNoCache();
        $this->assertNull($cache->cards(Book::class, [$book->id])[0]->extra('stars'));

        $cache->extend('stars', static fn (Collection $books, string $locale): array => $books->mapWithKeys(static fn (Book $book): array => [$book->getKey() => 'stars of '.$book->getKey().' in '.$locale])->all());
        $this->assertSame('stars of '.$book->id.' in en', $cache->cards(Book::class, [$book->id])[0]->extra('stars'));

        // Added again under the same name: replaced.
        $cache->extend('stars', static fn (): array => [$book->id => 'replaced']);
        $this->assertSame('replaced', $cache->cards(Book::class, [$book->id])[0]->extra('stars'));

        // Another name: both are there.
        $cache->extend('hearts', static fn (): array => [$book->id => 'loved']);
        $card = $cache->cards(Book::class, [$book->id])[0];
        $this->assertSame('replaced', $card->extra('stars'));
        $this->assertSame('loved', $card->extra('hearts'));
    }

    public function test_the_thumbnails_of_the_pictures_come_from_what_the_module_gave(): void
    {
        [$book] = $this->books(1);
        $cache = $this->cardsWithNoCache();
        $this->assertSame([], $cache->cards(Book::class, [$book->id])[0]->media('cover')[0]['thumbnails']);

        $cache->thumbnailsUsing(static fn (string $path, string $url): array => ['small' => $url.'?w=100', 'path' => $path]);
        $thumbnails = $cache->cards(Book::class, [$book->id])[0]->media('cover')[0]['thumbnails'];

        $this->assertSame('books/cover-1.jpg', $thumbnails['path']);
        $this->assertStringEndsWith('?w=100', $thumbnails['small']);

        $cache->thumbnailsUsing(null);
        $this->assertSame([], $cache->cards(Book::class, [$book->id])[0]->media('cover')[0]['thumbnails']);
    }
}

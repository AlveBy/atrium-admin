<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\Card;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Code;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Stringable;

/**
 * Lists answered with ids: what they are kept under, what makes them stale,
 * how the pages are cut, and the ids a visitor gets back.
 */
final class RecordCacheListsTest extends CacheTestCase
{
    private RecordCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->app->make(RecordCache::class);
    }

    /**
     * @return array<int,Book>
     */
    private function freshBooks(int $count): array
    {
        $books = $this->books($count);
        $this->app->make(ContentLanguages::class)->default();
        $this->resetRequest();

        return $books;
    }

    public function test_the_ids_of_text_keys_come_back_as_numbers_where_they_are_numbers(): void
    {
        foreach (['12', '123456789012345678', '1234567890123456789', '12abc', 'abc'] as $code) {
            Code::query()->create(['code' => $code]);
        }
        $this->resetRequest();

        $first = $this->cache->ids(Code::query()->orderBy('code'));
        $this->resetRequest();
        $again = $this->cache->ids(Code::query()->orderBy('code'));

        foreach ([$first, $again] as $result) {
            $this->assertCount(5, $result->ids);
            $this->assertSame(5, $result->total);
            // Up to eighteen digits it is a number; longer would not fit; the rest is text.
            $this->assertContains(12, $result->ids, 'twelve');
            $this->assertContains(123456789012345678, $result->ids, 'eighteen digits');
            $this->assertContains('1234567890123456789', $result->ids, 'nineteen digits');
            $this->assertContains('12abc', $result->ids, 'digits and letters');
            $this->assertContains('abc', $result->ids, 'letters');
        }
    }

    public function test_with_the_cache_off_the_ids_come_straight_from_the_database(): void
    {
        foreach (['12', 'abc'] as $code) {
            Code::query()->create(['code' => $code]);
        }
        [$cache, $repository] = $this->spyCache();
        config(['atrium.cache.enabled' => false]);

        $all = $cache->ids(Code::query()->orderBy('code'));
        $page = $cache->ids(Code::query()->orderBy('code'), 1, 2);

        $this->assertSame([12, 'abc'], $all->ids);
        $this->assertSame(2, $all->total);
        $this->assertSame(['abc'], $page->ids);
        $this->assertSame(2, $page->total);
        $this->assertSame([], $repository->calls);
        $this->assertSame([], $cache->readNames());
        $this->assertSame(['hits' => 0, 'misses' => 0], $cache->requestStats());
    }

    public function test_a_list_tells_the_page_what_it_depends_on(): void
    {
        $this->freshBooks(1);

        $this->cache->ids(Book::query());

        $this->assertSame(['all', 'site:0', 'list:'.Book::class], $this->cache->readNames());
    }

    public function test_a_list_that_reads_other_classes_depends_on_them_too(): void
    {
        $this->freshBooks(1);

        $this->cache->ids(Book::query(), dependsOn: [Shelf::class, Code::class]);

        $this->assertSame(['all', 'site:0', 'list:'.Book::class, 'list:'.Shelf::class, 'list:'.Code::class], $this->cache->readNames());
    }

    public function test_a_list_goes_stale_when_a_class_it_depends_on_changes_and_not_otherwise(): void
    {
        [$book] = $this->freshBooks(1);
        $withShelf = fn (array $dependsOn) => $this->cache->ids(Book::query()->whereHas('shelf', fn ($shelves) => $shelves->whereNotNull('name')), dependsOn: $dependsOn);
        $this->assertSame([$book->id], $withShelf([Shelf::class])->ids);
        $this->assertSame([$book->id], $withShelf([])->ids);

        DB::table('cache_shelves')->update(['name' => null]);
        $this->cache->changed(Shelf::query()->firstOrFail());
        $this->resetRequest();

        $this->assertSame([], $withShelf([Shelf::class])->ids);
        // Not told about the shelves, it keeps what it had.
        $this->assertSame([$book->id], $withShelf([])->ids);
    }

    public function test_a_page_of_a_list_is_cut_by_its_size_and_number(): void
    {
        $books = $this->freshBooks(5);
        $ids = array_map(fn (Book $book): int => $book->id, $books);

        $first = $this->cache->ids(Book::query()->orderBy('id'), 2, 1);
        $second = $this->cache->ids(Book::query()->orderBy('id'), 2, 2);
        $last = $this->cache->ids(Book::query()->orderBy('id'), 2, 3);
        $beyond = $this->cache->ids(Book::query()->orderBy('id'), 2, 4);

        $this->assertSame(array_slice($ids, 0, 2), $first->ids);
        $this->assertSame(array_slice($ids, 2, 2), $second->ids);
        $this->assertSame(array_slice($ids, 4, 2), $last->ids);
        $this->assertSame([], $beyond->ids);
        $this->assertSame([5, 5, 5, 5], [$first->total, $second->total, $last->total, $beyond->total]);
    }

    public function test_no_page_number_means_the_first_page(): void
    {
        $books = $this->freshBooks(3);

        $result = $this->cache->ids(Book::query()->orderBy('id'), 2);

        $this->assertSame([$books[0]->id, $books[1]->id], $result->ids);
    }

    public function test_a_list_without_a_page_size_gives_every_id(): void
    {
        $books = $this->freshBooks(3);

        $result = $this->cache->ids(Book::query()->orderBy('id'));

        $this->assertSame(array_map(fn (Book $book): int => $book->id, $books), $result->ids);
        $this->assertSame(3, $result->total);
    }

    public function test_an_empty_page_costs_one_query_for_the_count_and_none_for_the_ids(): void
    {
        $this->freshBooks(2);

        [$queries, $result] = $this->countQueries(fn () => $this->cache->ids(Book::query()->where('price', '>', 999999), 5, 1));

        $this->assertSame(1, $queries);
        $this->assertSame([], $result->ids);
        $this->assertSame(0, $result->total);
    }

    public function test_the_query_of_the_caller_is_left_as_it_was(): void
    {
        $this->freshBooks(3);
        $query = Book::query()->where('price', '>', 500)->orderBy('id');
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->cache->ids($query, 2, 2);
        $this->cache->ids($query);
        $this->cache->ids($query, 1, 1);

        $this->assertSame($sql, $query->toSql());
        $this->assertSame($bindings, $query->getBindings());
        $this->assertCount(3, $query->get());
    }

    public function test_a_list_is_read_back_from_the_cache_with_no_query(): void
    {
        $books = $this->freshBooks(3);
        $first = $this->cache->ids(Book::query()->orderBy('id'), 2, 1);
        $this->resetRequest();

        [$queries, $again] = $this->countQueries(fn () => $this->cache->ids(Book::query()->orderBy('id'), 2, 1));

        $this->assertSame(0, $queries);
        $this->assertSame($first->ids, $again->ids);
        $this->assertSame(3, $again->total);
        $this->assertSame($books[0]->id, $again->ids[0]);
    }

    public function test_misses_and_hits_are_counted_once_each(): void
    {
        $this->freshBooks(2);

        $this->cache->ids(Book::query());
        $this->assertSame(['hits' => 0, 'misses' => 1], $this->cache->requestStats());

        $this->cache->ids(Book::query());
        $this->assertSame(['hits' => 1, 'misses' => 1], $this->cache->requestStats());
    }

    public function test_a_list_is_kept_apart_for_each_filter_page_size_page_and_language(): void
    {
        $this->freshBooks(5);
        $kept = function (callable $query): int {
            $before = $this->cache->requestStats()['misses'];
            $query();

            return $this->cache->requestStats()['misses'] - $before;
        };

        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 2, 1)));
        $this->assertSame(0, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 2, 1)), 'the same list');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 2000), 2, 1)), 'another filter');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 3, 1)), 'another page size');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 2, 2)), 'another page');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 2)), 'no page');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000))), 'no page size');

        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '<', 1000), 2, 1)), 'the same value in another comparison');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->orderBy('id'))), 'an order and no value at all');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->orderByDesc('id'))), 'another order');

        $this->app->setLocale('de');
        $this->assertSame(1, $kept(fn () => $this->cache->ids(Book::query()->where('price', '>', 1000), 2, 1)), 'another language');
    }

    public function test_a_time_in_a_filter_is_told_apart_to_the_microsecond(): void
    {
        $this->freshBooks(1);
        $before = fn (string $time) => $this->cache->ids(Book::query()->where('created_at', '<', Carbon::parse($time)));

        $before('2031-01-01 00:00:00.100000');
        $before('2031-01-01 00:00:00.200000');
        $this->assertSame(['hits' => 0, 'misses' => 2], $this->cache->requestStats());

        $before('2031-01-01 00:00:00.100000');
        $this->assertSame(['hits' => 1, 'misses' => 2], $this->cache->requestStats());
    }

    public function test_a_filter_with_a_value_that_prints_as_text_is_told_apart_by_that_text(): void
    {
        $this->freshBooks(3);
        $amount = fn (string $text): Stringable => new class($text) implements Stringable
        {
            public function __construct(private readonly string $text) {}

            public function __toString(): string
            {
                return $this->text;
            }
        };
        $over = fn (string $text) => $this->cache->ids(Book::query()->whereRaw('price > ?', [$amount($text)]));

        $this->assertCount(2, $over('1000')->ids);
        $this->assertCount(1, $over('2000')->ids);
        $this->assertCount(2, $over('1000')->ids);
        $this->assertSame(['hits' => 1, 'misses' => 2], $this->cache->requestStats());
    }

    public function test_a_list_is_kept_under_a_key_of_its_versions_and_query_for_an_hour(): void
    {
        [$cache, $repository] = $this->spyCache();
        $this->freshBooks(1);

        $cache->ids(Book::query());

        $stamp = $cache->versions()->stamp(['all', 'site:0', 'list:'.Book::class]);
        $put = $repository->callsTo('putMany');
        $this->assertCount(1, $put);
        $this->assertCount(1, $put[0][0]);
        $this->assertMatchesRegularExpression('/^atrium:ids:'.$stamp.':[0-9a-f]{32}$/', (string) array_key_first($put[0][0]));
        $this->assertSame(3600, $put[0][1]);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:int}>
     */
    public static function listLifetimes(): array
    {
        return [
            'an hour by default' => [[], 3600],
            'what is configured' => [['lists' => 90], 90],
            'a string from the environment' => [['lists' => '45'], 45],
            'zero is one second' => [['lists' => 0], 1],
            'only the cards configured' => [['cards' => 5], 3600],
        ];
    }

    /**
     * @param  array<string,mixed>  $ttl
     */
    #[DataProvider('listLifetimes')]
    public function test_lists_live_as_long_as_the_configuration_says(array $ttl, int $seconds): void
    {
        config(['atrium.cache.ttl' => $ttl]);
        [$cache, $repository] = $this->spyCache();
        $this->freshBooks(1);

        $cache->ids(Book::query());

        $this->assertSame($seconds, $repository->callsTo('putMany')[0][1]);
    }

    public function test_a_paginator_holds_the_cards_of_the_page_twenty_to_a_page_unless_told_otherwise(): void
    {
        $this->freshBooks(25);

        $page = $this->cache->paginate(Book::query()->orderBy('id'));

        $this->assertSame(20, $page->perPage());
        $this->assertSame(25, $page->total());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(2, $page->lastPage());
        $this->assertCount(20, $page->items());
        $this->assertContainsOnlyInstancesOf(Card::class, $page->items());
    }

    public function test_a_paginator_knows_its_page_its_size_its_name_and_its_path(): void
    {
        $this->freshBooks(5);
        Paginator::currentPathResolver(fn (): string => '/books');

        $page = $this->cache->paginate(Book::query()->orderBy('id'), 2, 'p', 3);

        $this->assertSame(2, $page->perPage());
        $this->assertSame(3, $page->currentPage());
        $this->assertSame('p', $page->getPageName());
        $this->assertSame('/books', $page->path());
        $this->assertSame('/books?p=2', $page->url(2));
        $this->assertCount(1, $page->items());
    }

    public function test_a_paginator_takes_its_page_from_the_request_under_its_own_name(): void
    {
        $books = $this->freshBooks(5);
        Paginator::currentPageResolver(fn (string $name): int => $name === 'p' ? 2 : 1);

        $page = $this->cache->paginate(Book::query()->orderBy('id'), 2, 'p');

        $this->assertSame(2, $page->currentPage());
        $this->assertSame([$books[2]->id, $books[3]->id], array_map(fn (Card $card): int|string => $card->id(), $page->items()));
    }

    public function test_a_paginator_hands_the_language_and_the_dependencies_on(): void
    {
        [$book] = $this->freshBooks(1);
        $book->setTranslation('name', 'de', 'Buch')->save();
        $this->resetRequest();

        $page = $this->cache->paginate(Book::query()->whereHas('shelf', fn ($shelves) => $shelves->whereNotNull('name')), 5, 'page', 1, 'de', [Shelf::class]);

        $this->assertSame('Buch', $page->items()[0]->name);
        $this->assertContains('list:'.Shelf::class, $this->cache->readNames());
    }
}

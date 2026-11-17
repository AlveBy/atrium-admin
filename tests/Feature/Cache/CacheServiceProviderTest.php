<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\Card;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the panel's service provider wires the record cache to: the query
 * macros, the listeners of the models, the end of a request or a job and the
 * numbers handed to monitoring.
 */
final class CacheServiceProviderTest extends CacheTestCase
{
    private function cache(): RecordCache
    {
        return $this->app->make(RecordCache::class);
    }

    /**
     * @return array<int,Book>
     */
    private function twentyFiveBooks(): array
    {
        return $this->books(25);
    }

    public function test_the_record_cache_is_one_for_the_whole_application_with_the_store_and_versions_it_shares(): void
    {
        $cache = $this->app->make(RecordCache::class);

        $this->assertSame($cache, $this->app->make(RecordCache::class));
        $this->assertSame($this->app->make(CacheStore::class), $this->app->make(CacheStore::class));
        $this->assertSame($this->app->make(Versions::class), $this->app->make(Versions::class));
        $this->assertSame($this->app->make(CacheStore::class), $cache->store());
        $this->assertSame($this->app->make(Versions::class), $cache->versions());
    }

    public function test_cached_ids_take_a_page_and_the_classes_the_list_depends_on(): void
    {
        $books = $this->books(5);

        $page = Book::query()->orderBy('id')->cachedIds(2, 2, [Shelf::class]);

        $this->assertSame([$books[2]->id, $books[3]->id], $page->ids);
        $this->assertSame(5, $page->total);
        $this->assertContains('list:'.Shelf::class, $this->cache()->readNames());
    }

    public function test_cached_ids_without_a_page_answer_all_of_them_and_depend_on_nothing_else(): void
    {
        $books = $this->books(3);
        $this->resetRequest();

        $all = Book::query()->orderBy('id')->cachedIds();

        $this->assertSame(array_map(fn (Book $book): int => $book->id, $books), $all->ids);
        $this->assertNotContains('list:'.Shelf::class, $this->cache()->readNames());
    }

    public function test_cached_paginate_pages_by_twenty_unless_told_otherwise(): void
    {
        $this->twentyFiveBooks();

        $page = Book::query()->orderBy('id')->cachedPaginate();

        $this->assertSame(20, $page->perPage());
        $this->assertSame(25, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertSame('page', $page->getPageName());
        $this->assertSame(1, $page->currentPage());
    }

    public function test_cached_paginate_passes_its_page_name_page_language_and_classes_on(): void
    {
        $books = $this->books(5);
        foreach ($books as $book) {
            $book->setTranslation('name', 'de', 'Buch '.$book->id)->save();
        }
        $this->resetRequest();

        $page = Book::query()->orderBy('id')->cachedPaginate(2, 'seite', 2, 'de', [Shelf::class]);

        $this->assertSame(2, $page->perPage());
        $this->assertSame('seite', $page->getPageName());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame([$books[2]->id, $books[3]->id], array_map(fn (Card $card): int|string => $card->id(), $page->items()));
        $this->assertSame('de', $page->items()[0]->locale());
        $this->assertContains('list:'.Shelf::class, $this->cache()->readNames());
    }

    public function test_cached_cards_are_the_cards_of_the_query_in_a_language_of_choice(): void
    {
        $books = $this->books(3);
        $this->resetRequest();

        $cards = Book::query()->orderByDesc('id')->cachedCards('de', [Shelf::class]);

        $this->assertSame([$books[2]->id, $books[1]->id, $books[0]->id], array_map(fn (Card $card): int|string => $card->id(), $cards));
        $this->assertSame('de', $cards[0]->locale());
        $this->assertContains('list:'.Shelf::class, $this->cache()->readNames());
    }

    public function test_cached_cards_are_in_the_current_language_when_none_is_named(): void
    {
        $this->books(1);
        $this->app->setLocale('de');

        $cards = Book::query()->cachedCards();

        $this->assertSame('de', $cards[0]->locale());
    }

    public function test_a_model_read_marks_its_class_as_read_while_the_page_cache_is_on(): void
    {
        $this->books(1);
        config(['atrium.cache.pages.enabled' => true]);
        $this->resetRequest();

        Book::query()->get();

        $this->assertSame(['list:'.Book::class], array_values(array_filter($this->cache()->readNames(), fn (string $name): bool => str_starts_with($name, 'list:'.Book::class))));
    }

    public function test_a_model_read_marks_nothing_while_the_page_cache_is_off(): void
    {
        $this->books(1);
        config(['atrium.cache.pages.enabled' => false]);
        $this->resetRequest();

        Book::query()->get();

        $this->assertSame([], $this->cache()->readNames());
    }

    public function test_the_page_cache_is_off_when_the_setting_is_missing(): void
    {
        $this->books(1);
        $pages = config('atrium.cache.pages');
        unset($pages['enabled']);
        config(['atrium.cache.pages' => $pages]);
        $this->resetRequest();

        Book::query()->get();

        $this->assertSame([], $this->cache()->readNames());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function modelEvents(): array
    {
        return [
            'saved' => ['saved'],
            'deleted' => ['deleted'],
            'restored' => ['restored'],
            'forceDeleted' => ['forceDeleted'],
        ];
    }

    #[DataProvider('modelEvents')]
    public function test_every_event_that_changes_a_record_makes_its_lists_stale(string $event): void
    {
        $shelf = Shelf::query()->create(['name' => ['en' => 'Shelf']]);
        $this->resetRequest();
        $before = $this->cache()->versions()->stamp(['list:'.Shelf::class]);

        Event::dispatch('eloquent.'.$event.': '.Shelf::class, [$shelf]);
        $this->resetRequest();

        $this->assertNotSame($before, $this->cache()->versions()->stamp(['list:'.Shelf::class]));
    }

    public function test_an_event_with_no_record_in_it_is_left_alone(): void
    {
        $shelf = Shelf::query()->create(['name' => ['en' => 'Shelf']]);
        $this->resetRequest();
        $before = $this->cache()->versions()->stamp(['list:'.Shelf::class]);

        Event::dispatch('eloquent.saved: '.Shelf::class, ['not a record']);
        Event::dispatch('eloquent.deleted: '.Shelf::class, []);
        $this->resetRequest();

        $this->assertSame($before, $this->cache()->versions()->stamp(['list:'.Shelf::class]));
        $this->assertNotNull($shelf->id);
    }

    public function test_the_end_of_a_request_counts_it_into_the_totals_and_starts_afresh(): void
    {
        [$book] = $this->books(1);
        $this->cache()->resetTotals();
        $this->resetRequest();

        Book::cached($book->id);
        $counted = $this->cache()->requestStats();
        $this->assertGreaterThan(0, $counted['misses']);

        $this->app->terminate();

        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache()->requestStats());
        $this->assertSame($counted, $this->cache()->totals());
    }

    public function test_the_start_of_a_job_counts_the_last_one_and_starts_afresh(): void
    {
        [$book] = $this->books(1);
        $this->cache()->resetTotals();
        $this->resetRequest();

        Book::cached($book->id);
        $counted = $this->cache()->requestStats();
        $this->assertGreaterThan(0, $counted['misses']);
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([]);

        Event::dispatch(new JobProcessing('sync', $job));

        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache()->requestStats());
        $this->assertSame($counted, $this->cache()->totals());
    }

    public function test_monitoring_gets_the_numbers_of_this_request_on_top_of_its_own(): void
    {
        [$a, $b, $c] = $this->books(3);
        Book::cached($a->id);
        $this->resetRequest();
        Book::cachedMany([$a->id, $b->id, $c->id]);
        $this->assertSame(['hits' => 1, 'misses' => 2], $this->cache()->requestStats());

        $stats = Hook::apply('atrium.cache.stats', ['hits' => 10, 'misses' => 20, 'entries' => 7]);

        $this->assertSame(['hits' => 11, 'misses' => 22, 'entries' => 7], $stats);
    }

    public function test_monitoring_gets_this_requests_numbers_alone_when_it_brought_none(): void
    {
        [$a, $b] = $this->books(2);
        Book::cached($a->id);
        $this->resetRequest();
        Book::cachedMany([$a->id, $b->id]);

        $this->assertSame(['hits' => 1, 'misses' => 1], Hook::apply('atrium.cache.stats', []));
    }

    public function test_the_numbers_monitoring_brought_are_read_as_numbers(): void
    {
        [$a] = $this->books(1);
        Book::cached($a->id);
        $this->resetRequest();
        Book::cached($a->id);

        $this->assertSame(['hits' => 6, 'misses' => 3], Hook::apply('atrium.cache.stats', ['hits' => '5', 'misses' => '3']));
    }

    public function test_the_warm_command_is_one_of_the_panels_commands(): void
    {
        $this->assertArrayHasKey('atrium:cache:warm', Artisan::all());
    }
}

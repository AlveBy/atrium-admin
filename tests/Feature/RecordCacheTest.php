<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\Card;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The record cache on a store with tags (the array store, like Redis):
 * cards, lists of ids, and no stale data after a save.
 */
class RecordCacheTest extends CacheTestCase
{
    public function test_cards_come_back_in_the_order_of_the_ids_and_skip_missing_ones(): void
    {
        [$a, $b, $c] = $this->books(3);

        $cards = Book::cachedMany([$c->id, 999, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], array_map(fn (Card $card): int|string => $card->id(), $cards));
    }

    public function test_a_warm_card_costs_no_query_and_one_cache_read(): void
    {
        $books = $this->books(5);
        $ids = array_map(fn (Book $book): int => $book->id, $books);

        Book::cachedMany($ids);
        $this->resetRequest();

        $reads = 0;
        $listener = function () use (&$reads): void {
            $reads++;
        };
        $this->app['events']->listen(RetrievingManyKeys::class, $listener);

        [$queries, $cards] = $this->countQueries(fn (): array => Book::cachedMany($ids));

        $this->assertSame(0, $queries);
        $this->assertCount(5, $cards);
        // One multi-get of the versions, one of the cards.
        $this->assertLessThanOrEqual(2, $reads);
        $this->assertSame('Shelf 1', $cards[0]->relation('shelf')?->name);
    }

    public function test_building_cards_costs_the_same_queries_for_ten_records_as_for_forty(): void
    {
        // Whatever is read once per request (the languages) is read first.
        Book::cachedMany([$this->books(1)[0]->id]);
        $ten = array_map(fn (Book $book): int => $book->id, $this->books(10));
        $forty = array_map(fn (Book $book): int => $book->id, $this->books(40));

        [$few] = $this->countQueries(fn (): array => Book::cachedMany($ten));
        [$many] = $this->countQueries(fn (): array => Book::cachedMany($forty));

        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(5, $many);
    }

    public function test_a_card_holds_the_record_in_its_language_with_relations_seo_and_pictures(): void
    {
        [$book] = $this->books(1);
        $book->setTranslation('name', 'de', 'Buch 1')->save();
        $book->seoMeta()->create(['title' => 'Read Book 1']);

        $en = Book::cached($book->id, 'en');
        $de = Book::cached($book->id, 'de');

        $this->assertSame('Book 1', $en->name);
        $this->assertSame('Buch 1', $de->get('name'));
        $this->assertSame('Read Book 1', $en->seo('title'));
        $this->assertSame(['red', 'blue'], $en->relation('tags')->pluck('name')->all());
        $this->assertStringEndsWith('/storage/books/cover-1.jpg', (string) $en->picture('cover'));
        $this->assertCount(2, $en->media('photos'));
        $this->assertInstanceOf(Book::class, $en->model());
        $this->assertTrue($en->model()->relationLoaded('shelf'));
        $this->assertSame(1000, $en['price']);

        // A card keeps the card's language and the default one, not all of them.
        // MySQL keeps the keys of a JSON column in an order of its own.
        $languages = array_keys(json_decode($de->raw()['name'], true));
        sort($languages);
        $this->assertSame(['de', 'en'], $languages);
    }

    public function test_saving_a_record_drops_its_cards_in_every_language(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id, 'en');
        Book::cached($book->id, 'de');

        $book->setTranslation('name', 'en', 'Renamed')->setTranslation('name', 'de', 'Umbenannt')->save();
        $this->resetRequest();

        $this->assertSame('Renamed', Book::cached($book->id, 'en')->name);
        $this->assertSame('Umbenannt', Book::cached($book->id, 'de')->name);
    }

    public function test_a_deleted_record_is_gone_from_cards_and_lists(): void
    {
        [$a, $b] = $this->books(2);
        $this->assertSame([$a->id, $b->id], Book::query()->orderBy('id')->cachedIds()->ids);
        Book::cachedMany([$a->id, $b->id]);

        $a->delete();
        $this->resetRequest();

        $this->assertSame([$b->id], Book::query()->orderBy('id')->cachedIds()->ids);
        $this->assertNull(Book::cached($a->id));
    }

    public function test_renaming_a_related_record_rebuilds_the_cards_that_show_it(): void
    {
        [$book] = $this->books(1);
        $this->assertSame('Shelf 1', Book::cached($book->id)->relation('shelf')->name);

        $book->shelf->setTranslation('name', 'en', 'Moved shelf')->save();
        $this->resetRequest();

        $this->assertSame('Moved shelf', Book::cached($book->id)->relation('shelf')->name);
    }

    public function test_saving_seo_meta_drops_the_owners_card(): void
    {
        [$book] = $this->books(1);
        $this->assertSame('Book 1', Book::cached($book->id)->seo('title'));

        SeoMeta::query()->create(['seoable_type' => Book::class, 'seoable_id' => $book->id, 'title' => 'New title']);
        $this->resetRequest();

        $this->assertSame('New title', Book::cached($book->id)->seo('title'));
    }

    public function test_lists_are_cached_by_query_page_and_language(): void
    {
        $this->books(5);

        $first = Book::query()->orderByDesc('price')->cachedIds(2, 1);
        $this->resetRequest();

        [$queries, $again] = $this->countQueries(fn () => Book::query()->orderByDesc('price')->cachedIds(2, 1));
        $second = Book::query()->orderByDesc('price')->cachedIds(2, 2);

        $this->assertSame(0, $queries);
        $this->assertSame($first->ids, $again->ids);
        $this->assertSame(5, $again->total);
        $this->assertNotSame($first->ids, $second->ids);
    }

    public function test_a_new_record_shows_in_the_list_at_once(): void
    {
        $this->books(2);
        $this->assertSame(2, Book::query()->cachedIds(10, 1)->total);

        $this->books(1, 'Extra');
        $this->resetRequest();

        $this->assertSame(3, Book::query()->cachedIds(10, 1)->total);
    }

    public function test_cached_paginate_gives_a_paginator_of_cards(): void
    {
        $this->books(5);

        $page = Book::query()->orderBy('id')->cachedPaginate(2, page: 3);

        $this->assertSame(5, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertInstanceOf(Card::class, $page->items()[0]);
    }

    public function test_an_admin_save_leaves_nothing_stale(): void
    {
        [$book] = $this->books(1);
        $tag = Tag::query()->create(['name' => 'green']);
        Book::cached($book->id);
        Book::query()->where('price', '>', 500)->cachedIds();

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/books/{$book->id}", [
                'name' => ['en' => 'Edited', 'de' => 'Bearbeitet'],
                'active' => true,
                'price' => 10,
                'shelf_id' => $book->shelf_id,
                'tags' => [$tag->id],
                'seo_title' => 'Edited title',
            ])
            ->assertRedirect();

        $this->resetRequest();
        $card = Book::cached($book->id);

        $this->assertSame('Edited', $card->name);
        $this->assertSame(['green'], $card->relation('tags')->pluck('name')->all());
        $this->assertSame('Edited title', $card->seo('title'));
        $this->assertSame([], Book::query()->where('price', '>', 500)->cachedIds()->ids);
    }

    public function test_a_save_in_a_transaction_drops_again_once_committed(): void
    {
        [$book] = $this->books(1);
        $versions = $this->app->make(RecordCache::class)->versions();
        $name = 'list:'.Book::class;
        $before = $versions->get([$name])[$name];
        $during = null;

        DB::transaction(function () use ($book, $versions, $name, &$during): void {
            $book->update(['price' => 5]);
            // The request that saves reads its own change at once.
            $during = $versions->get([$name])[$name];
        });

        $this->assertNotSame($before, $during);
        // A visitor may have kept the old row between the save and the commit.
        $this->assertNotSame($during, $versions->get([$name])[$name]);
    }

    public function test_a_rolled_back_save_drops_nothing_more(): void
    {
        [$book] = $this->books(1);
        $versions = $this->app->make(RecordCache::class)->versions();
        $name = 'list:'.Book::class;
        $during = null;

        try {
            DB::transaction(function () use ($book, $versions, $name, &$during): void {
                $book->update(['price' => 5]);
                $during = $versions->get([$name])[$name];

                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame($during, $versions->get([$name])[$name]);
    }

    public function test_a_bulk_import_drops_the_cache_once_at_the_end(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);
        $cache = $this->app->make(RecordCache::class);

        $cache->bulk(function () use ($book): void {
            $book->setTranslation('name', 'en', 'Imported')->save();
            $this->resetRequest();
            // Not dropped yet: the import is still running.
            $this->assertSame('Book 1', Book::cached($book->id)->name);
        });

        $this->resetRequest();
        $this->assertSame('Imported', Book::cached($book->id)->name);
    }

    public function test_remember_keeps_a_value_until_a_record_of_its_class_changes(): void
    {
        $cache = $this->app->make(RecordCache::class);
        $calls = 0;
        $count = function () use (&$calls): int {
            $calls++;

            return Book::query()->count();
        };

        $this->assertSame(0, $cache->remember(Book::class, 'count', $count));
        $this->assertSame(0, $cache->remember(Book::class, 'count', $count));
        $this->books(1);
        $this->resetRequest();

        $this->assertSame(1, $cache->remember(Book::class, 'count', $count));
        $this->assertSame(2, $calls);
    }

    public function test_clearing_a_module_rebuilds_its_cards(): void
    {
        [$book] = $this->books(1);
        Book::cached($book->id);
        DB::table('cache_books')->update(['price' => 1]);

        $this->app->make(RecordCache::class)->flushClass(Book::class);
        $this->resetRequest();

        $this->assertSame(1, Book::cached($book->id)->price);
    }

    public function test_time_in_list_queries_moves_in_steps(): void
    {
        Carbon::setTestNow('2026-11-17 10:15:42');

        $this->assertSame('2026-11-17 10:15:00', $this->app->make(RecordCache::class)->now()->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_with_the_cache_off_everything_is_read_from_the_database(): void
    {
        config(['atrium.cache.enabled' => false]);
        [$book] = $this->books(1);
        Book::cached($book->id);
        DB::table('cache_books')->update(['price' => 7]);

        $this->assertSame(7, Book::cached($book->id)->price);
    }
}

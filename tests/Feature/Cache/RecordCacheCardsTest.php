<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\Card;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Code;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Stringable;

/**
 * Cards out of the cache: which ids, what is read, what is kept and for how
 * long, what the request remembers of it.
 */
final class RecordCacheCardsTest extends CacheTestCase
{
    private RecordCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->app->make(RecordCache::class);
    }

    /**
     * What a request starts with once the records are in the database: the
     * sites and languages loaded, nothing read, nothing counted.
     *
     * @return array<int,Book>
     */
    private function freshBooks(int $count): array
    {
        $books = $this->books($count);
        $this->app->make(ContentLanguages::class)->default();
        $this->resetRequest();

        return $books;
    }

    /**
     * @param  array<int,Card>  $cards
     * @return array<int,int|string>
     */
    private function ids(array $cards): array
    {
        return array_map(static fn (Card $card): int|string => $card->id(), $cards);
    }

    /**
     * @return array<int,string>
     */
    private function cardNames(string $class, int $site = 0): array
    {
        return ['all', 'site:'.$site, 'card:'.$class, ...array_map(static fn (string $dependency): string => 'list:'.$dependency, $this->cache->builder()->dependenciesOf($class))];
    }

    public function test_ids_may_come_from_a_generator_whose_keys_repeat(): void
    {
        [$a, $b] = $this->freshBooks(2);

        $cards = $this->cache->cards(Book::class, (function () use ($a, $b) {
            yield 'same' => $a->id;
            yield 'same' => $b->id;
        })());

        $this->assertSame([$a->id, $b->id], $this->ids($cards));
    }

    public function test_ids_may_come_from_an_array_with_any_keys(): void
    {
        [$a, $b] = $this->freshBooks(2);

        $cards = $this->cache->cards(Book::class, ['x' => $b->id, 7 => $a->id]);

        $this->assertSame([$b->id, $a->id], $this->ids($cards));
    }

    public function test_a_record_asked_for_twice_is_built_and_counted_once(): void
    {
        [$a, $b] = $this->freshBooks(2);

        $cards = $this->cache->cards(Book::class, [$a->id, $a->id, $b->id, $a->id]);

        $this->assertSame([$a->id, $b->id], $this->ids($cards));
        $this->assertSame(['hits' => 0, 'misses' => 2], $this->cache->requestStats());
    }

    public function test_an_id_written_another_way_is_the_same_record(): void
    {
        [$a, $b] = $this->freshBooks(2);

        $cards = $this->cache->cards(Book::class, [$a->id, '0'.$a->id, (string) $a->id, $b->id]);

        $this->assertSame([$a->id, $b->id], $this->ids($cards));
        $this->assertSame(['hits' => 0, 'misses' => 2], $this->cache->requestStats());
    }

    public function test_no_ids_give_no_cards_and_read_nothing(): void
    {
        $this->freshBooks(1);

        $this->assertSame([], $this->cache->cards(Book::class, []));
        $this->assertSame([], $this->cache->cards(Book::class, (fn () => yield from [])()));
        $this->assertSame([], $this->cache->readNames());
        $this->assertSame(['hits' => 0, 'misses' => 0], $this->cache->requestStats());
    }

    public function test_cards_tell_the_page_what_it_depends_on(): void
    {
        [$book] = $this->freshBooks(1);

        $this->cache->cards(Book::class, [$book->id]);

        $this->assertSame(['list:'.Book::class, 'all', 'site:0', 'card:'.Book::class, 'list:'.Shelf::class, 'list:'.Tag::class], $this->cache->readNames());
    }

    public function test_with_the_cache_off_a_page_depends_on_the_class_only(): void
    {
        [$book] = $this->freshBooks(1);
        config(['atrium.cache.enabled' => false]);

        $this->cache->cards(Book::class, [$book->id]);

        $this->assertSame(['list:'.Book::class], $this->cache->readNames());
    }

    public function test_a_class_that_is_not_cacheable_is_built_every_time_and_depends_on_its_class_only(): void
    {
        [$cache, $repository] = $this->spyCache();
        $red = Tag::query()->firstOrCreate(['name' => 'red']);
        $this->resetRequest();

        $cards = $cache->cards(Tag::class, [$red->id]);

        $this->assertSame([$red->id], $this->ids($cards));
        $this->assertSame('red', $cards[0]->name);
        $this->assertSame([], $repository->calls);
        $this->assertSame(['list:'.Tag::class], $cache->readNames());
        $this->assertSame(['hits' => 0, 'misses' => 0], $cache->requestStats());
    }

    public function test_with_the_cache_off_nothing_is_read_from_or_written_to_it(): void
    {
        [$cache, $repository] = $this->spyCache();
        [$book] = $this->freshBooks(1);
        config(['atrium.cache.enabled' => false]);

        $cards = $cache->cards(Book::class, [$book->id]);

        $this->assertSame([$book->id], $this->ids($cards));
        $this->assertSame([], $repository->calls);
        $this->assertSame(['hits' => 0, 'misses' => 0], $cache->requestStats());
    }

    public function test_hits_and_misses_add_up_over_the_calls_of_a_request(): void
    {
        [$a, $b, $c] = $this->freshBooks(3);

        $this->cache->cards(Book::class, [$a->id, $b->id]);
        $this->assertSame(['hits' => 0, 'misses' => 2], $this->cache->requestStats());

        $this->cache->cards(Book::class, [$a->id, $b->id, $c->id]);
        $this->assertSame(['hits' => 2, 'misses' => 3], $this->cache->requestStats());

        $this->cache->cards(Book::class, [$a->id, $c->id]);
        $this->assertSame(['hits' => 4, 'misses' => 3], $this->cache->requestStats());
    }

    public function test_cards_are_kept_under_a_key_of_the_class_site_language_and_id_for_a_day(): void
    {
        [$cache, $repository] = $this->spyCache();
        [$book] = $this->freshBooks(1);

        $cache->cards(Book::class, [$book->id], 'de');

        $key = 'atrium:card:'.$cache->versions()->stamp($this->cardNames(Book::class)).':0:de:'.$book->id;
        $this->assertContains([$key], array_map(static fn (array $call): array => $call[0], $repository->callsTo('many')));
        $this->assertSame([$key], array_keys($repository->callsTo('putMany')[0][0]));
        $this->assertSame(86400, $repository->callsTo('putMany')[0][1]);
        $this->assertSame($book->id, $repository->callsTo('putMany')[0][0][$key]['id']);
        $this->assertSame('de', $repository->callsTo('putMany')[0][0][$key]['locale']);
    }

    public function test_a_warm_card_is_read_back_and_nothing_is_written(): void
    {
        [$cache, $repository] = $this->spyCache();
        [$book] = $this->freshBooks(1);
        $cache->cards(Book::class, [$book->id]);
        $writes = count($repository->callsTo('putMany'));

        $cards = $cache->cards(Book::class, [$book->id]);

        $this->assertSame([$book->id], $this->ids($cards));
        $this->assertCount($writes, $repository->callsTo('putMany'));
        $this->assertSame(['hits' => 1, 'misses' => 1], $cache->requestStats());
    }

    public function test_cards_speak_the_language_of_the_app_unless_asked_for_another(): void
    {
        [$book] = $this->freshBooks(1);
        $book->setTranslation('name', 'de', 'Buch')->save();
        $this->app->setLocale('de');

        $this->assertSame('de', $this->cache->cards(Book::class, [$book->id])[0]->locale());
        $this->assertSame('en', $this->cache->cards(Book::class, [$book->id], 'en')[0]->locale());
        $this->assertSame('Buch', $this->cache->cards(Book::class, [$book->id])[0]->name);
    }

    public function test_cards_of_a_site_are_kept_apart_from_the_cards_of_another(): void
    {
        [$cache, $repository] = $this->spyCache();
        $site = Site::query()->create(['code' => 'shop', 'name' => 'Shop', 'active' => true]);
        $this->app->make(Sites::class)->flush();
        $this->app->make(Sites::class)->setCurrent($site);
        [$book] = $this->freshBooks(1);

        $cache->cards(Book::class, [$book->id]);

        $key = 'atrium:card:'.$cache->versions()->stamp($this->cardNames(Book::class, (int) $site->id)).':'.$site->id.':en:'.$book->id;
        $this->assertSame([$key], array_keys($repository->callsTo('putMany')[0][0]));
        $this->assertContains('site:'.$site->id, $cache->readNames());
        $this->assertNotContains('site:0', $cache->readNames());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:int}>
     */
    public static function cardLifetimes(): array
    {
        return [
            'a day by default' => [[], 86400],
            'what is configured' => [['cards' => 120], 120],
            'a string from the environment' => [['cards' => '300'], 300],
            'zero is one second' => [['cards' => 0], 1],
            'negative is one second' => [['cards' => -5], 1],
            'only the lists configured' => [['lists' => 5], 86400],
        ];
    }

    /**
     * @param  array<string,mixed>  $ttl
     */
    #[DataProvider('cardLifetimes')]
    public function test_cards_live_as_long_as_the_configuration_says(array $ttl, int $seconds): void
    {
        config(['atrium.cache.ttl' => $ttl]);
        [$cache, $repository] = $this->spyCache();
        [$book] = $this->freshBooks(1);

        $cache->cards(Book::class, [$book->id]);

        $this->assertSame($seconds, $repository->callsTo('putMany')[0][1]);
    }

    public function test_a_key_may_be_anything_that_prints_as_one(): void
    {
        Code::query()->create(['code' => 'abc']);
        $key = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'abc';
            }
        };

        $cards = $this->cache->cards(Code::class, [$key, 'abc']);

        $this->assertCount(1, $cards);
        $this->assertSame('abc', $cards[0]->id());
    }
}

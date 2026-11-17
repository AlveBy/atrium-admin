<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Node;
use Atrium\Admin\Tests\Fixtures\Cache\Part;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\SpyRepository;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * What saving a record, flushing and importing do to the counters and the
 * cards, read off a store that writes down every call.
 */
final class RecordCacheChangesTest extends CacheTestCase
{
    private RecordCache $cache;

    private SpyRepository $store;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->cache, $this->store] = $this->spyCache();
        $this->app->make(ContentLanguages::class)->default();
        Node::$told = 0;
    }

    /**
     * The version counters bumped, in order.
     *
     * @return array<int,string>
     */
    private function bumped(): array
    {
        return array_map(static fn (array $call): string => substr((string) $call[0], strlen('atrium:v:')), $this->store->callsTo('increment'));
    }

    /**
     * The keys forgotten, in order.
     *
     * @return array<int,string>
     */
    private function forgotten(): array
    {
        return array_map(static fn (array $call): string => (string) $call[0], $this->store->callsTo('forget'));
    }

    /**
     * @param  array<int,class-string>  $dependencies
     */
    private function cardKey(string $class, array $dependencies, int $site, string $locale, int|string $id): string
    {
        $names = ['all', 'site:'.$site, 'card:'.$class, ...array_map(static fn (string $dependency): string => 'list:'.$dependency, $dependencies)];

        return 'atrium:card:'.$this->cache->versions()->stamp($names).':'.$site.':'.$locale.':'.$id;
    }

    private function book(int $id): Book
    {
        $book = new Book;
        $book->setAttribute('id', $id);

        return $book;
    }

    /**
     * Make the application know these sites, without saving any.
     *
     * @param  array<int,int|string|null>  $ids
     */
    private function knowSites(array $ids): void
    {
        $sites = new Collection(array_map(static fn (int|string|null $id): Site => (new Site)->setRawAttributes(['id' => $id], true), $ids));
        (new ReflectionProperty(Sites::class, 'all'))->setValue($this->app->make(Sites::class), $sites);
    }

    public function test_saving_a_book_makes_the_lists_of_its_class_stale_and_nothing_else(): void
    {
        $this->cache->changed($this->book(5));

        $this->assertSame(['list:'.Book::class], $this->bumped());
    }

    public function test_saving_a_record_that_is_not_cacheable_makes_its_lists_stale_and_drops_no_card(): void
    {
        $tag = new Tag;
        $tag->setAttribute('id', 5);

        $this->cache->changed($tag);

        $this->assertSame(['list:'.Tag::class], $this->bumped());
        $this->assertSame([], $this->forgotten());
    }

    public function test_saving_a_site_makes_that_sites_pages_stale(): void
    {
        $site = new Site;
        $site->setAttribute('id', 3);

        $this->cache->changed($site);

        $this->assertSame(['list:'.Site::class, 'site:3'], $this->bumped());
    }

    public function test_a_site_that_has_no_key_yet_makes_no_site_stale(): void
    {
        $this->cache->changed(new Site);

        $this->assertSame(['list:'.Site::class], $this->bumped());
    }

    /**
     * @return array<string,array{0:?int}>
     */
    public static function languageKeys(): array
    {
        return [
            'a saved language' => [4],
            'one with no key yet' => [null],
        ];
    }

    #[DataProvider('languageKeys')]
    public function test_saving_a_language_makes_everything_stale(?int $key): void
    {
        $language = new Language;
        $language->setAttribute('id', $key);

        $this->cache->changed($language);

        $this->assertSame(['list:'.Language::class, 'all'], $this->bumped());
    }

    public function test_saving_a_language_drops_the_entries_of_the_store_where_it_can(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->changed(new Language);

        $this->assertContains([['atrium']], $repository->callsTo('tags'));
    }

    public function test_saving_a_book_leaves_the_store_alone_beyond_its_own_cards(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->changed($this->book(5));

        $this->assertNotContains([['atrium']], $repository->callsTo('tags'));
    }

    public function test_a_record_of_a_class_that_is_ignored_changes_nothing(): void
    {
        config(['atrium.cache.ignore' => [Book::class]]);

        $this->cache->changed($this->book(5));
        $this->assertSame([], $this->store->calls);

        $tag = new Tag;
        $tag->setAttribute('id', 5);
        $this->cache->changed($tag);
        $this->assertSame(['list:'.Tag::class], $this->bumped());
    }

    public function test_a_record_that_has_no_key_drops_no_card_and_a_record_that_has_one_drops_them(): void
    {
        $this->cache->changed(new Book);
        $this->assertSame([], $this->forgotten());

        $this->cache->changed($this->book(5));
        $this->assertNotSame([], $this->forgotten());
    }

    public function test_saving_a_record_drops_its_card_in_every_site_and_language(): void
    {
        $this->knowSites([3, 5]);
        $this->app->setLocale('fr');

        $this->cache->changed($this->book(7));

        $expected = [];

        foreach ([0, 3, 5] as $site) {
            foreach (['fr', 'de', 'en'] as $locale) {
                $expected[] = $this->cardKey(Book::class, [Shelf::class, Tag::class], $site, $locale, 7);
            }
        }

        $forgotten = $this->forgotten();
        $this->assertEqualsCanonicalizing($expected, array_unique($forgotten));
        // Dropped again once committed: the test runs inside a transaction.
        $this->assertCount(count($expected) * 2, $forgotten);
    }

    public function test_a_site_or_a_language_listed_twice_is_dropped_once(): void
    {
        $this->knowSites([3, 3]);
        $this->app->setLocale('de');

        $this->cache->changed($this->book(7));

        $keys = $this->forgotten();
        // Sites 0 and 3, languages de and en, each key twice for the second drop.
        $this->assertCount(8, $keys);
        $this->assertCount(4, array_unique($keys));
    }

    public function test_the_first_site_is_always_known_and_a_site_without_a_key_adds_none(): void
    {
        $this->knowSites([null]);
        $this->app->setLocale('en');

        $this->cache->changed($this->book(7));

        $this->assertEqualsCanonicalizing(
            [
                $this->cardKey(Book::class, [Shelf::class, Tag::class], 0, 'en', 7),
                $this->cardKey(Book::class, [Shelf::class, Tag::class], 0, 'de', 7),
            ],
            array_unique($this->forgotten()),
        );
    }

    public function test_a_site_that_has_no_key_does_not_hide_the_others(): void
    {
        $this->knowSites([3, null, 5]);

        $this->cache->changed($this->book(7));

        $forgotten = $this->forgotten();
        $this->assertContains($this->cardKey(Book::class, [Shelf::class, Tag::class], 3, 'en', 7), $forgotten);
        $this->assertContains($this->cardKey(Book::class, [Shelf::class, Tag::class], 5, 'en', 7), $forgotten);
        $this->assertContains($this->cardKey(Book::class, [Shelf::class, Tag::class], 0, 'en', 7), $forgotten);
    }

    public function test_the_key_of_a_site_that_comes_as_text_is_a_number(): void
    {
        $this->knowSites(['5']);

        $this->cache->changed($this->book(7));

        $this->assertContains($this->cardKey(Book::class, [Shelf::class, Tag::class], 5, 'en', 7), $this->forgotten());
    }

    public function test_when_the_sites_cannot_be_read_the_main_one_is_dropped_and_nothing_is_reported(): void
    {
        Exceptions::fake();
        $this->app->instance(Sites::class, new class
        {
            public function all(): never
            {
                throw new RuntimeException('No sites.');
            }

            public function isResolved(): bool
            {
                return false;
            }
        });

        $this->cache->changed($this->book(7));

        Exceptions::assertNothingReported();
        $this->assertContains($this->cardKey(Book::class, [Shelf::class, Tag::class], 0, 'en', 7), $this->forgotten());
        $this->assertCount(2, array_unique($this->forgotten()));
    }

    public function test_when_the_languages_cannot_be_read_those_of_the_application_are_dropped_and_nothing_is_reported(): void
    {
        Exceptions::fake();
        $this->knowSites([]);
        $this->app->setLocale('fr');
        DB::listen(static function ($query): void {
            if (str_contains($query->sql, (new Language)->getTable())) {
                throw new RuntimeException('No languages.');
            }
        });

        $this->cache->changed($this->book(7));

        Exceptions::assertNothingReported();
        $this->assertEqualsCanonicalizing(
            [
                $this->cardKey(Book::class, [Shelf::class, Tag::class], 0, 'fr', 7),
            ],
            array_unique($this->forgotten()),
        );
    }

    public function test_saving_a_record_drops_the_cards_of_the_records_of_its_class_that_point_at_it(): void
    {
        $one = Part::query()->create(['name' => 'one']);
        $child = Part::query()->create(['name' => 'child', 'parent_id' => $one->id]);
        $other = Part::query()->create(['name' => 'other', 'parent_id' => $one->id + 100]);
        $this->store->calls = [];

        $this->cache->changed($one);

        $forgotten = $this->forgotten();
        $this->assertContains($this->cardKey(Part::class, [], 0, 'en', $one->id), $forgotten);
        $this->assertContains($this->cardKey(Part::class, [], 0, 'en', $child->id), $forgotten);
        $this->assertNotContains($this->cardKey(Part::class, [], 0, 'en', $other->id), $forgotten);
    }

    public function test_dropping_cards_is_not_reading_them(): void
    {
        $this->cache->changed($this->book(7));

        $this->assertSame([], $this->cache->readNames());
    }

    public function test_a_save_in_a_transaction_drops_the_cards_again_once_committed_and_only_then(): void
    {
        $this->knowSites([]);
        $this->app->setLocale('en');
        $during = null;

        DB::transaction(function () use (&$during): void {
            $this->store->calls = [];
            $this->cache->changed($this->book(7));
            $during = count($this->forgotten());
        });

        $this->assertSame(2, $during);
        $this->assertSame(4, count($this->forgotten()));
        $this->assertSame(array_slice($this->forgotten(), 0, 2), array_slice($this->forgotten(), 2));
    }

    public function test_a_save_in_a_transaction_that_is_rolled_back_drops_nothing_more(): void
    {
        $this->knowSites([]);

        try {
            DB::transaction(function (): void {
                $this->cache->changed($this->book(7));

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertCount(2, $this->forgotten());
    }

    public function test_a_cache_that_is_down_is_reported_and_the_save_goes_on(): void
    {
        Exceptions::fake();
        $this->store->failing = 'increment';

        $this->cache->changed($this->book(7));

        Exceptions::assertReported(static fn (RuntimeException $e): bool => $e->getMessage() === 'The cache is down.');
    }

    public function test_a_record_is_not_reported_when_the_cache_is_up(): void
    {
        Exceptions::fake();

        $this->cache->changed($this->book(7));
        $this->cache->changed(new Node);

        Exceptions::assertNothingReported();
    }

    public function test_a_record_that_failed_to_save_the_cache_may_be_saved_again(): void
    {
        $handler = new class implements ExceptionHandler
        {
            public function report(Throwable $e): void
            {
                throw new LogicException('The handler is down too.');
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e): never
            {
                throw $e;
            }

            public function renderForConsole($output, Throwable $e): void {}
        };
        $this->app->instance(ExceptionHandler::class, $handler);
        $this->store->failing = 'increment';

        try {
            $this->cache->changed($this->book(7));
            $this->fail('The handler was meant to throw.');
        } catch (LogicException) {
        }

        $this->store->failing = null;
        $this->store->calls = [];
        $this->cache->changed($this->book(7));

        $this->assertSame(['list:'.Book::class], $this->bumped());
    }

    public function test_the_same_record_is_not_saved_again_while_it_is_being_saved(): void
    {
        $one = Node::query()->create(['name' => 'one']);
        $two = Node::query()->create(['name' => 'two']);
        $one->tells = [$two];
        $two->tells = [$one];
        Node::$told = 0;

        $this->cache->changed($one);

        // One after the other once each, not for ever.
        $this->assertSame(['list:'.Node::class, 'list:'.Node::class], $this->bumped());
        $this->assertLessThan(50, Node::$told);
    }

    public function test_a_record_that_tells_another_of_its_class_makes_both_stale(): void
    {
        $one = Node::query()->create(['name' => 'one']);
        $two = Node::query()->create(['name' => 'two']);
        $one->tells = [$two];

        $this->cache->changed($one);

        $this->assertSame(['list:'.Node::class, 'list:'.Node::class], $this->bumped());
    }

    public function test_a_record_that_tells_one_of_another_class_with_the_same_key_makes_both_stale(): void
    {
        $node = Node::query()->create(['name' => 'node']);
        $book = $this->book((int) $node->getKey());
        $node->tells = [$book];

        $this->cache->changed($node);

        $this->assertSame(['list:'.Node::class, 'list:'.Book::class], $this->bumped());
    }

    public function test_a_record_may_be_saved_again_once_its_save_is_done(): void
    {
        $this->cache->changed($this->book(7));
        $this->cache->changed($this->book(7));

        $this->assertSame(['list:'.Book::class, 'list:'.Book::class], $this->bumped());
    }

    public function test_a_record_with_no_key_is_saved_each_time_it_is_asked_to(): void
    {
        $this->cache->changed(new Node);
        $this->cache->changed(new Node);

        $this->assertSame(['list:'.Node::class, 'list:'.Node::class], $this->bumped());
    }

    /**
     * @return array<string,array{0:mixed,1:int}> what the record answers, the lists made stale
     */
    public static function whoToTell(): array
    {
        return [
            'nobody' => [[], 1],
            'nothing' => [null, 1],
            'one record' => [[new Book], 2],
            'something that is not a record' => [[new stdClass, 'text', 5], 1],
            'a record after something that is not' => [[new stdClass, new Book], 2],
            'one record not in a list' => [new Book, 1],
        ];
    }

    #[DataProvider('whoToTell')]
    public function test_only_records_are_told_about_a_save(mixed $answer, int $lists): void
    {
        Exceptions::fake();
        $node = new Node;
        $node->setAttribute('id', 1);
        $node->tells = $answer;

        $this->cache->changed($node);

        Exceptions::assertNothingReported();
        $this->assertCount($lists, $this->bumped());
    }

    public function test_the_class_flush_makes_cards_and_lists_stale_and_drops_the_group_of_the_store(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->flushClass(Book::class);

        $this->assertSame(['card:'.Book::class, 'list:'.Book::class], array_map(static fn (array $call): string => substr((string) $call[0], strlen('atrium:v:')), $repository->callsTo('increment')));
        $this->assertSame([[['atrium:Atrium.Admin.Tests.Fixtures.Cache.Book']]], $repository->callsTo('tags'));
    }

    public function test_the_class_flush_of_a_name_of_ones_own_works_the_same(): void
    {
        $this->cache->flushClass('my-block');

        $this->assertSame(['card:my-block', 'list:my-block'], $this->bumped());
    }

    public function test_the_flush_of_a_site_makes_only_that_site_stale(): void
    {
        $this->cache->flushSite(4);

        $this->assertSame(['site:4'], $this->bumped());
    }

    public function test_the_flush_of_everything_makes_everything_stale_and_drops_what_the_store_holds(): void
    {
        [$cache, $repository] = $this->spyCache(true);

        $cache->flushAll();

        $this->assertSame(['atrium:v:all'], array_map(static fn (array $call): string => (string) $call[0], $repository->callsTo('increment')));
        $this->assertSame([[['atrium']]], $repository->callsTo('tags'));
    }

    public function test_a_store_without_tags_is_not_asked_to_drop_anything(): void
    {
        $this->cache->flushAll();
        $this->cache->flushClass(Book::class);

        $this->assertSame([], $this->store->callsTo('tags'));
    }

    public function test_nothing_is_dropped_while_an_import_runs(): void
    {
        $this->cache->bulk(function (): void {
            $this->cache->changed($this->book(1));
            $this->cache->changed($this->book(2));
            $this->cache->changed(new Language);

            $this->assertSame([], $this->store->calls);
        });
    }

    public function test_an_import_drops_each_class_it_touched_once_at_the_end(): void
    {
        $tag = new Tag;
        $tag->setAttribute('id', 1);

        $this->cache->bulk(function () use ($tag): void {
            $this->cache->changed($this->book(1));
            $this->cache->changed($tag);
            $this->cache->changed($this->book(2));
            $this->cache->changed($this->book(1));
        });

        $this->assertSame(['card:'.Book::class, 'list:'.Book::class, 'card:'.Tag::class, 'list:'.Tag::class], $this->bumped());
        $this->assertSame([], $this->forgotten());
    }

    public function test_an_import_that_touched_nothing_drops_nothing(): void
    {
        $this->cache->bulk(static fn (): null => null);

        $this->assertSame([], $this->store->calls);
    }

    public function test_an_import_that_writes_past_the_models_drops_everything_once_and_not_by_class(): void
    {
        $this->cache->bulk(function (): void {
            $this->cache->changed($this->book(1));
        }, everything: true);

        $this->assertSame(['all'], $this->bumped());
    }

    public function test_an_import_that_does_not_say_so_does_not_drop_everything(): void
    {
        $this->cache->bulk(static fn (): null => null, everything: false);

        $this->assertNotContains('all', $this->bumped());
    }

    public function test_an_import_inside_an_import_waits_for_the_outer_one(): void
    {
        $this->cache->bulk(function (): void {
            $this->cache->bulk(function (): void {
                $this->cache->changed($this->book(1));
            });

            $this->assertSame([], $this->store->calls, 'the inner one ended');

            $this->cache->changed($this->book(2));
        });

        $this->assertSame(['card:'.Book::class, 'list:'.Book::class], $this->bumped());
    }

    /**
     * @return array<string,array{0:bool,1:bool}> whether the inner and the outer ask for everything
     */
    public static function nestedImports(): array
    {
        return [
            'the inner one' => [true, false],
            'the outer one' => [false, true],
            'both' => [true, true],
        ];
    }

    #[DataProvider('nestedImports')]
    public function test_everything_goes_when_any_of_the_nested_imports_asks_for_it(bool $inner, bool $outer): void
    {
        $this->cache->bulk(function () use ($inner): void {
            $this->cache->bulk(static fn (): null => null, $inner);
            $this->assertSame([], $this->store->calls);
        }, $outer);

        $this->assertSame(['all'], $this->bumped());
    }

    public function test_the_next_import_starts_afresh(): void
    {
        $this->cache->bulk(function (): void {
            $this->cache->changed($this->book(1));
        }, everything: true);
        $this->store->calls = [];

        $this->cache->bulk(function (): void {
            $this->cache->changed(new Node);
        });

        $this->assertSame(['card:'.Node::class, 'list:'.Node::class], $this->bumped());
    }

    public function test_an_import_gives_back_what_its_callback_returns(): void
    {
        $this->assertSame(42, $this->cache->bulk(static fn (): int => 42));
        $this->assertSame('x', $this->cache->bulk(static fn (): string => 'x', everything: true));
    }

    public function test_an_import_that_fails_still_drops_what_it_touched_and_lets_the_error_through(): void
    {
        try {
            $this->cache->bulk(function (): void {
                $this->cache->changed($this->book(1));

                throw new RuntimeException('Import failed.');
            });
            $this->fail('The error must come through.');
        } catch (RuntimeException $e) {
            $this->assertSame('Import failed.', $e->getMessage());
        }

        $this->assertSame(['card:'.Book::class, 'list:'.Book::class], $this->bumped());

        // And saving works again: the import is over.
        $this->store->calls = [];
        $this->cache->changed($this->book(1));
        $this->assertSame(['list:'.Book::class], $this->bumped());
    }
}

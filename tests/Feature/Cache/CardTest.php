<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\Card;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * One record as the cache keeps it: read back as an array, through methods
 * and as a model, with no query.
 */
final class CardTest extends CacheTestCase
{
    /**
     * @param  array<string,mixed>  $overrides
     */
    private function card(array $overrides = []): Card
    {
        return new Card($overrides + [
            'id' => 5,
            'type' => Book::class,
            'locale' => 'de',
            'site' => 0,
            'raw' => ['id' => 5, 'name' => json_encode(['en' => 'Book', 'de' => 'Buch']), 'price' => '1000', 'active' => 1, 'shelf_id' => 1],
            'relations' => [],
            'seo' => ['title' => 'Read it', 'description' => '', 'keywords' => null],
            'media' => ['cover' => [['url' => '/storage/a.jpg', 'path' => 'a.jpg', 'name' => 'a.jpg', 'thumbnails' => []]], 'photos' => []],
            'url' => '/books/book',
            'extra' => ['stars' => 4, 'empty' => null],
        ]);
    }

    public function test_a_card_tells_what_it_was_built_from(): void
    {
        $card = $this->card();

        $this->assertSame(5, $card->id());
        $this->assertSame(Book::class, $card->type());
        $this->assertSame('de', $card->locale());
        $this->assertSame(['id' => 5, 'name' => json_encode(['en' => 'Book', 'de' => 'Buch']), 'price' => '1000', 'active' => 1, 'shelf_id' => 1], $card->raw());
    }

    public function test_an_id_may_be_a_string(): void
    {
        $this->assertSame('abc', $this->card(['id' => 'abc'])->id());
    }

    public function test_an_attribute_comes_through_the_casts_and_the_cards_language(): void
    {
        $card = $this->card();

        $this->assertSame(1000, $card->get('price'));
        $this->assertTrue($card->get('active'));
        // The card is German whatever the app speaks now.
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('Buch', $card->get('name'));
    }

    public function test_a_missing_attribute_gives_the_default_and_a_present_one_does_not(): void
    {
        $card = $this->card();

        $this->assertNull($card->get('nothing'));
        $this->assertSame('fallback', $card->get('nothing', 'fallback'));
        $this->assertSame(1000, $card->get('price', 'fallback'));
    }

    public function test_a_record_without_translations_is_read_as_it_is(): void
    {
        $card = new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'de', 'raw' => ['id' => 1, 'name' => 'red']]);

        $this->assertSame('red', $card->get('name'));
        $this->assertInstanceOf(Tag::class, $card->model());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:?string}>
     */
    public static function seoValues(): array
    {
        return [
            'a title' => [['title' => 'Read it'], 'Read it'],
            'an empty one' => [['title' => ''], null],
            'nothing' => [['title' => null], null],
            'a number' => [['title' => 5], null],
            'a list' => [['title' => ['a']], null],
            'a missing key' => [[], null],
        ];
    }

    /**
     * @param  array<string,mixed>  $seo
     */
    #[DataProvider('seoValues')]
    public function test_seo_gives_a_filled_in_string_or_nothing(array $seo, ?string $expected): void
    {
        $this->assertSame($expected, $this->card(['seo' => $seo])->seo('title'));
    }

    public function test_seo_of_a_card_that_has_none_is_nothing(): void
    {
        $card = new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'en', 'raw' => []]);

        $this->assertNull($card->seo('title'));
    }

    public function test_the_pictures_of_a_field_or_none(): void
    {
        $card = $this->card();

        $this->assertSame('/storage/a.jpg', $card->media('cover')[0]['url']);
        $this->assertSame([], $card->media('photos'));
        $this->assertSame([], $card->media('unknown'));
        $this->assertSame([], (new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'en', 'raw' => []]))->media('cover'));
    }

    public function test_the_picture_is_the_first_one_of_the_first_filled_in_field(): void
    {
        $card = $this->card();

        $this->assertSame('/storage/a.jpg', $card->picture('cover'));
        $this->assertSame('/storage/a.jpg', $card->picture('photos', 'unknown', 'cover'));
        $this->assertNull($card->picture('photos', 'unknown'));
        $this->assertNull($card->picture());
    }

    public function test_the_address_is_a_string_or_nothing(): void
    {
        $this->assertSame('/books/book', $this->card()->url());
        $this->assertNull($this->card(['url' => null])->url());
        $this->assertNull($this->card(['url' => 5])->url());
        $this->assertNull((new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'en', 'raw' => []]))->url());
    }

    public function test_what_a_module_added_or_the_default(): void
    {
        $card = $this->card();

        $this->assertSame(4, $card->extra('stars'));
        $this->assertSame(4, $card->extra('stars', 'none'));
        $this->assertNull($card->extra('unknown'));
        $this->assertSame('none', $card->extra('unknown', 'none'));
        $this->assertSame('none', $card->extra('empty', 'none'));
        $this->assertSame('none', (new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'en', 'raw' => []]))->extra('stars', 'none'));
    }

    public function test_the_model_is_one_object_that_reads_like_the_record_a_query_gives(): void
    {
        [$book] = $this->books(1);
        $card = Book::cached($book->id);

        $model = $card->model();

        $this->assertInstanceOf(Book::class, $model);
        $this->assertSame($model, $card->model());
        // Named like a model read by a query: it is the same record.
        $this->assertSame(Book::query()->find($book->id)?->getConnectionName(), $model->getConnectionName());
        $this->assertTrue($model->is(Book::query()->find($book->id)));
        $this->assertTrue($model->exists);
    }

    public function test_the_model_may_be_asked_for_by_its_class(): void
    {
        [$book] = $this->books(1);
        $card = Book::cached($book->id);

        $this->assertInstanceOf(Book::class, $card->model(Book::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The card holds a '.Book::class.', not a '.Shelf::class.'.');

        $card->model(Shelf::class);
    }

    public function test_relations_come_from_the_card_with_no_query(): void
    {
        [$book] = $this->books(1);
        $card = Book::cached($book->id);

        [$queries, $relations] = $this->countQueries(fn (): array => [
            $card->relation('shelf'),
            $card->relation('tags'),
            $card->relation('seoMeta'),
            $card->model()->relationLoaded('tags'),
        ]);

        $this->assertSame(0, $queries);
        $this->assertInstanceOf(Shelf::class, $relations[0]);
        $this->assertSame('Shelf 1', $relations[0]->name);
        $this->assertCount(2, $relations[1]);
        $this->assertContainsOnlyInstancesOf(Tag::class, $relations[1]);
        $this->assertSame(['red', 'blue'], $relations[1]->pluck('name')->all());
        // A relation the record has none of is known to be empty, not looked up.
        $this->assertNull($relations[2]);
        $this->assertTrue($relations[3]);
    }

    public function test_a_relation_that_is_empty_in_the_card_is_not_loaded_from_the_database(): void
    {
        $shelf = Shelf::query()->create(['name' => ['en' => 'Real']]);
        $card = $this->card([
            'raw' => ['id' => 5, 'name' => '{}', 'shelf_id' => $shelf->id],
            'relations' => ['shelf' => null, 'tags' => []],
        ]);

        [$queries, $found] = $this->countQueries(fn (): array => [$card->relation('shelf'), $card->relation('tags')]);

        $this->assertSame(0, $queries);
        $this->assertNull($found[0]);
        $this->assertCount(0, $found[1]);
    }

    public function test_a_card_is_read_only(): void
    {
        $card = $this->card();

        foreach ([
            fn () => $card['price'] = 1,
            function () use ($card): void {
                unset($card['price']);
            },
        ] as $write) {
            try {
                $write();
                $this->fail('A card must not be written to.');
            } catch (LogicException $e) {
                $this->assertSame('A cached record is read-only.', $e->getMessage());
            }
        }
    }

    public function test_a_card_reads_like_an_array_and_like_an_object(): void
    {
        $card = $this->card();

        $this->assertSame(1000, $card['price']);
        $this->assertSame(1000, $card->price);
        $this->assertTrue(isset($card['price']));
        $this->assertTrue(isset($card->price));
        $this->assertFalse(isset($card['nothing']));
        $this->assertFalse(isset($card->nothing));
        $this->assertNull($card['nothing']);
    }

    public function test_a_card_is_its_data_as_an_array(): void
    {
        $card = $this->card();

        $this->assertSame(5, $card->toArray()['id']);
        $this->assertSame(Book::class, $card->toArray()['type']);
        $this->assertSame(['title' => 'Read it', 'description' => '', 'keywords' => null], $card->toArray()['seo']);
    }

    public function test_a_card_is_written_as_json_with_its_attributes(): void
    {
        $card = $this->card();

        $this->assertSame([
            'id' => 5,
            'type' => Book::class,
            'locale' => 'de',
            'attributes' => $card->model()->attributesToArray(),
            'seo' => ['title' => 'Read it', 'description' => '', 'keywords' => null],
            'media' => $card->toArray()['media'],
            'url' => '/books/book',
            'extra' => ['stars' => 4, 'empty' => null],
        ], $card->jsonSerialize());
        $this->assertSame(json_encode($card->jsonSerialize()), json_encode($card));
    }

    public function test_a_card_without_seo_pictures_address_and_extras_is_written_with_empty_ones(): void
    {
        $card = new Card(['id' => 1, 'type' => Tag::class, 'locale' => 'en', 'raw' => ['id' => 1, 'name' => 'red']]);

        $this->assertSame([
            'id' => 1,
            'type' => Tag::class,
            'locale' => 'en',
            'attributes' => ['id' => 1, 'name' => 'red'],
            'seo' => [],
            'media' => [],
            'url' => null,
            'extra' => [],
        ], $card->jsonSerialize());
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\Card;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\Fixtures\Cache\Chapter;
use Atrium\Admin\Tests\Fixtures\Cache\Shelf;
use Atrium\Admin\Tests\Fixtures\Cache\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * Opting a model into the record cache, and the rows that belong to another
 * record telling their owner when they change.
 */
final class CachesRecordsTest extends CacheTestCase
{
    public function test_a_card_carries_the_relations_the_form_links_to_and_the_seo_meta(): void
    {
        $this->assertSame(['shelf', 'tags', 'seoMeta'], (new Book)->cacheRelations());
    }

    public function test_a_form_field_is_a_relation_only_when_it_names_one_of_another_class(): void
    {
        // Said twice a relation counts once; a tree select counts like a belongs-to; "owner" has
        // no "_id" to take the name from; "chapter" points to its own class; "fake" is not
        // a relation; "broken" fails; "ghost" does not exist; the form has no SEO meta.
        $this->assertSame(['shelf', 'section'], (new Chapter)->cacheRelations());
    }

    public function test_a_field_named_like_a_query_method_does_not_run_that_query(): void
    {
        $this->books(1);

        [$queries] = $this->countQueries(fn (): array => (new Chapter)->cacheRelations());

        // "count_id" would be count(), asked of the table.
        $this->assertSame(0, $queries);
    }

    public function test_a_model_with_no_form_carries_no_relation(): void
    {
        $this->assertSame([], (new Shelf)->cacheRelations());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function methods(): array
    {
        return ['cachedMany' => ['cachedMany'], 'cached' => ['cached']];
    }

    #[DataProvider('methods')]
    public function test_the_ways_to_ask_for_cards_are_public_static_methods(string $method): void
    {
        $reflection = new ReflectionMethod(Book::class, $method);

        $this->assertTrue($reflection->isPublic());
        $this->assertTrue($reflection->isStatic());
    }

    public function test_cached_many_and_cached_give_cards_in_the_order_asked_for(): void
    {
        [$a, $b] = $this->books(2);

        $cards = Book::cachedMany([$b->id, $a->id]);

        $this->assertSame([$b->id, $a->id], array_map(fn (Card $card): int|string => $card->id(), $cards));
        $this->assertSame($a->id, Book::cached($a->id)?->id());
        $this->assertSame($a->id, Book::cached((string) $a->id)?->id());
        $this->assertNull(Book::cached(9999));
    }

    public function test_cached_speaks_the_language_asked_for(): void
    {
        [$book] = $this->books(1);
        $book->setTranslation('name', 'de', 'Buch')->save();

        $this->assertSame('Buch', Book::cached($book->id, 'de')?->name);
        $this->assertSame('Book 1', Book::cachedMany([$book->id], 'en')[0]->name);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array<int,class-string<Model>>}>
     */
    public static function owners(): array
    {
        return [
            'a class' => [['seoable_type' => Book::class, 'seoable_id' => 7], [Book::class]],
            'a number as text' => [['seoable_type' => Book::class, 'seoable_id' => '7'], [Book::class]],
            'a class that is not a model' => [['seoable_type' => 'stdClass', 'seoable_id' => 7], []],
            'a class that does not exist' => [['seoable_type' => 'Nowhere\\Nothing', 'seoable_id' => 7], []],
            'no type' => [['seoable_type' => null, 'seoable_id' => 7], []],
            'no id' => [['seoable_type' => Book::class, 'seoable_id' => null], []],
            'nothing at all' => [[], []],
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  array<int,class-string<Model>>  $classes
     */
    #[DataProvider('owners')]
    public function test_a_row_names_the_owner_it_belongs_to_without_loading_it(array $attributes, array $classes): void
    {
        $row = new SeoMeta;
        $row->setRawAttributes($attributes, true);

        [$queries, $owners] = $this->countQueries(fn (): array => $row->cacheTouches());

        $this->assertSame(0, $queries);
        $this->assertSame($classes, array_map(static fn (Model $owner): string => $owner::class, $owners));

        foreach ($owners as $owner) {
            $this->assertSame(7, (int) $owner->getKey());
            $this->assertTrue($owner->exists);
            // Named, not changed: nothing to save.
            $this->assertFalse($owner->isDirty());
        }
    }

    public function test_a_row_may_name_its_owner_by_a_morph_alias(): void
    {
        Relation::morphMap(['book' => Book::class]);

        try {
            $row = new SeoMeta;
            $row->setRawAttributes(['seoable_type' => 'book', 'seoable_id' => 3], true);

            $owners = $row->cacheTouches();

            $this->assertCount(1, $owners);
            $this->assertInstanceOf(Book::class, $owners[0]);
            $this->assertSame(3, (int) $owners[0]->getKey());
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_a_slug_names_its_owner_by_its_own_morph_pair(): void
    {
        $slug = new Slug;
        $slug->setRawAttributes(['sluggable_type' => Tag::class, 'sluggable_id' => 4], true);

        $owners = $slug->cacheTouches();

        $this->assertCount(1, $owners);
        $this->assertInstanceOf(Tag::class, $owners[0]);
        $this->assertSame(4, (int) $owners[0]->getKey());
    }
}

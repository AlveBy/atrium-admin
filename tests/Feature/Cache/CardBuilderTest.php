<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use ArrayObject;
use Atrium\Admin\Cache\CardBuilder;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Feature\CacheTestCase;
use Atrium\Admin\Tests\Fixtures\Cards\CountingImageField;
use Atrium\Admin\Tests\Fixtures\Cards\Item;
use Atrium\Admin\Tests\Fixtures\Cards\Label;
use Atrium\Admin\Tests\Fixtures\Cards\Owner;
use Atrium\Admin\Tests\Fixtures\Cards\Section;
use Atrium\Admin\Tests\Fixtures\Cards\SeoBroken;
use Atrium\Admin\Tests\Fixtures\Cards\SeoLoose;
use Atrium\Admin\Tests\Fixtures\Cards\SeoTitleOnly;
use Error;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * What a card is made of, and what the builder knows about the class it
 * builds for: its relations, the classes its cards show something of, the
 * records that show it.
 */
final class CardBuilderTest extends CacheTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('card_owners', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
        });

        Schema::create('card_labels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('card_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code')->nullable();
        });

        Schema::create('card_items', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->json('summary')->nullable();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('link_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('picture')->nullable();
            $table->text('files')->nullable();
            $table->text('docs')->nullable();
        });

        Schema::create('card_item_label', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('label_id');
        });

        Schema::create('card_item_related', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('related_id');
        });
    }

    protected function tearDown(): void
    {
        Item::forget();

        parent::tearDown();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function item(array $attributes = []): Item
    {
        return Item::query()->create($attributes + ['name' => ['en' => 'Item']]);
    }

    /**
     * @param  array<int,int|string>  $ids
     * @return array<int|string,array<string,mixed>>
     */
    private function build(array $ids, string $locale = 'en', ?CardBuilder $builder = null): array
    {
        return ($builder ?? new CardBuilder)->build(Item::class, $ids, $locale, 1);
    }

    private function url(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    public function test_nothing_asked_for_is_nothing_built_and_no_query_is_made(): void
    {
        $this->item();

        [$queries, $cards] = $this->countQueries(fn (): array => $this->build([]));

        $this->assertSame([], $cards);
        $this->assertSame(0, $queries);
    }

    public function test_a_card_holds_the_row_the_relations_the_seo_the_pictures_and_the_address(): void
    {
        config(['atrium.public_urls.'.Item::class => ['prefix' => 'items']]);
        Item::declare(['owner', 'tags', 'link', 'seoMeta']);
        $owner = Owner::query()->create(['name' => ['en' => 'Ann', 'de' => 'Anna']]);
        $red = Label::query()->create(['name' => 'red']);
        $blue = Label::query()->create(['name' => 'blue']);
        $item = $this->item([
            'name' => ['en' => 'Hello', 'de' => 'Hallo'],
            'code' => 'hello',
            'owner_id' => $owner->id,
            'picture' => 'pics/a.jpg',
        ]);
        $item->tags()->attach([$red->id, $blue->id]);
        $item->updateSeoMeta(['title' => 'SEO title', 'description' => 'SEO description', 'keywords' => 'one, two', 'og_image' => 'seo/og.png']);

        $cards = $this->build([$item->id], 'de');
        $card = $cards[$item->id];

        $this->assertSame([$item->id], array_keys($cards));
        $this->assertSame($item->id, $card['id']);
        $this->assertSame(Item::class, $card['type']);
        $this->assertSame('de', $card['locale']);
        $this->assertSame(1, $card['site']);
        $this->assertSame(['id', 'type', 'locale', 'site', 'raw', 'relations', 'seo', 'media', 'url', 'extra'], array_keys($card));

        $fresh = Item::query()->findOrFail($item->id)->getAttributes();
        $raw = $card['raw'];
        $this->assertEquals(['en' => 'Hello', 'de' => 'Hallo'], json_decode((string) $raw['name'], true));
        unset($raw['name'], $fresh['name']);
        $this->assertEquals($fresh, $raw);

        $this->assertSame(['owner', 'tags', 'link', 'seoMeta'], array_keys($card['relations']));
        $this->assertSame(['id', 'type', 'raw', 'relations'], array_keys($card['relations']['owner']));
        $this->assertSame($owner->id, $card['relations']['owner']['id']);
        $this->assertSame(Owner::class, $card['relations']['owner']['type']);
        $this->assertSame([], $card['relations']['owner']['relations']);
        $this->assertEquals(['en' => 'Ann', 'de' => 'Anna'], json_decode((string) $card['relations']['owner']['raw']['name'], true));
        $this->assertEqualsCanonicalizing(
            [['id' => $red->id, 'type' => Label::class, 'raw' => ['id' => $red->id, 'name' => 'red'], 'relations' => []], ['id' => $blue->id, 'type' => Label::class, 'raw' => ['id' => $blue->id, 'name' => 'blue'], 'relations' => []]],
            $card['relations']['tags'],
        );
        $this->assertTrue(array_is_list($card['relations']['tags']));
        $this->assertNull($card['relations']['link']);
        $this->assertSame(SeoMeta::class, $card['relations']['seoMeta']['type']);

        $this->assertSame(['title' => 'SEO title', 'description' => 'SEO description', 'keywords' => 'one, two', 'og_image' => $this->url('/storage/seo/og.png')], $card['seo']);
        $this->assertSame(['picture' => [['url' => $this->url('/storage/pics/a.jpg'), 'path' => 'pics/a.jpg', 'name' => 'a.jpg', 'thumbnails' => []]]], $card['media']);
        $this->assertSame(app(Sites::class)->current()->url('items/hello'), $card['url']);
        $this->assertSame([], $card['extra']);
    }

    public function test_cards_are_keyed_by_id_and_records_that_are_missing_have_none(): void
    {
        [$a, $b] = [$this->item(), $this->item()];

        $cards = $this->build([$b->id, 9999, $a->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_keys($cards));
        $this->assertSame($a->id, $cards[$a->id]['id']);
        $this->assertSame($b->id, $cards[$b->id]['id']);
    }

    public function test_the_relations_cost_one_query_each_however_many_cards_there_are(): void
    {
        Item::declare(['owner', 'tags', 'seoMeta']);
        $owner = Owner::query()->create(['name' => ['en' => 'Ann']]);
        $label = Label::query()->create(['name' => 'red']);
        $ids = [];

        for ($i = 0; $i < 6; $i++) {
            $item = $this->item(['owner_id' => $owner->id, 'code' => 'item-'.$i]);
            $item->tags()->attach($label->id);
            $ids[] = $item->id;
        }

        $this->build($ids);

        [$queries, $cards] = $this->countQueries(fn (): array => $this->build($ids));

        $this->assertCount(6, $cards);
        // The items, the owners, the tags, the SEO meta.
        $this->assertSame(4, $queries);
    }

    public function test_a_card_is_built_in_its_own_language_and_the_application_gets_its_own_back(): void
    {
        $item = $this->item(['name' => ['en' => 'Hello', 'de' => 'Hallo']]);
        $this->app->setLocale('fr');
        $seen = [];
        $builder = new CardBuilder(['look' => function (Collection $models, string $locale) use (&$seen): array {
            $seen[] = [$locale, app()->getLocale()];

            return [];
        }]);

        $cards = $this->build([$item->id], 'de', $builder);

        $this->assertSame([['de', 'de']], $seen);
        $this->assertSame('fr', app()->getLocale());
        // The SEO title is the name, in the language the card is built in.
        $this->assertSame('Hallo', $cards[$item->id]['seo']['title']);
    }

    public function test_the_application_gets_its_language_back_when_the_build_fails(): void
    {
        $this->app->setLocale('fr');

        try {
            (new CardBuilder)->build('No\\Such\\Model', [1], 'de', 1);
            $this->fail('A class that does not exist cannot be built.');
        } catch (Error) {
            // expected
        }

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_a_module_adds_its_data_to_the_cards_and_a_broken_module_costs_only_its_own(): void
    {
        Exceptions::fake();
        [$a, $b, $c] = [$this->item(), $this->item(), $this->item()];
        $received = [];
        $builder = new CardBuilder([
            'stock' => function (Collection $models, string $locale) use (&$received, $a, $c): array {
                $received[] = [$models->modelKeys(), $locale];

                return [$a->id => 'in stock', $c->id => ['qty' => 3]];
            },
            'broken' => function (): array {
                throw new RuntimeException('the module is down');
            },
            'nothing' => fn (): mixed => null,
            'object' => fn (): ArrayObject => new ArrayObject([$b->id => 'shared']),
            'empty' => fn (): array => [$a->id => null],
        ]);

        $cards = $this->build([$a->id, $b->id, $c->id], 'de', $builder);

        $this->assertSame([[[$a->id, $b->id, $c->id], 'de']], $received);
        $this->assertSame(['stock' => 'in stock'], $cards[$a->id]['extra']);
        $this->assertSame(['object' => 'shared'], $cards[$b->id]['extra']);
        $this->assertSame(['stock' => ['qty' => 3]], $cards[$c->id]['extra']);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'the module is down');
        Exceptions::assertReportedCount(1);
    }

    public function test_the_pictures_get_their_thumbnails_from_the_resolver(): void
    {
        $item = $this->item(['picture' => 'pics/a.jpg']);
        $given = [];
        $builder = new CardBuilder([], function (string $path, string $url) use (&$given): array {
            $given[] = [$path, $url];

            return ['small' => $url.'?w=100'];
        });

        $card = $this->build([$item->id], 'en', $builder)[$item->id];

        $this->assertSame([['pics/a.jpg', $this->url('/storage/pics/a.jpg')]], $given);
        $this->assertSame(['small' => $this->url('/storage/pics/a.jpg').'?w=100'], $card['media']['picture'][0]['thumbnails']);
    }

    public function test_a_resolver_that_has_nothing_to_give_leaves_the_thumbnails_empty(): void
    {
        $item = $this->item(['picture' => 'pics/a.jpg']);
        $builder = new CardBuilder([], fn (): mixed => null);

        $card = $this->build([$item->id], 'en', $builder)[$item->id];

        $this->assertSame([], $card['media']['picture'][0]['thumbnails']);
    }

    public function test_an_address_that_cannot_be_worked_out_is_none_and_is_reported(): void
    {
        Exceptions::fake();
        config(['atrium.public_urls.'.Item::class => ['prefix' => 'items', 'parent' => 'section_id', 'tree' => 'No\\Such\\Tree']]);
        $item = $this->item(['code' => 'hello']);

        $card = $this->build([$item->id])[$item->id];

        $this->assertNull($card['url']);
        Exceptions::assertReported(Error::class);
        Exceptions::assertReportedCount(1);
    }

    public function test_a_record_of_a_class_without_pages_has_no_address(): void
    {
        $item = $this->item(['code' => 'hello']);

        $this->assertNull($this->build([$item->id])[$item->id]['url']);
    }

    public function test_the_row_of_a_translated_column_keeps_the_cards_language_and_the_default_one(): void
    {
        $item = $this->item([
            'name' => ['en' => 'Hello/World', 'de' => 'Hallo', 'ru' => 'Привет', 'fr' => 'Bonjour'],
            'summary' => ['en' => 'Short', 'ru' => 'Кратко', 'fr' => 'Court'],
        ]);

        $raw = $this->build([$item->id], 'ru')[$item->id]['raw'];

        // Readable, not escaped: the card is kept in the cache as it is.
        $this->assertStringContainsString('"ru":"Привет"', $raw['name']);
        $this->assertStringContainsString('"en":"Hello/World"', $raw['name']);
        $this->assertEquals(['en' => 'Hello/World', 'ru' => 'Привет'], json_decode($raw['name'], true));
        $this->assertEquals(['en' => 'Short', 'ru' => 'Кратко'], json_decode($raw['summary'], true));
    }

    public function test_a_translated_column_with_nothing_in_it_stays_empty(): void
    {
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"Hello"}', 'summary' => null]);

        $raw = $this->build([$id])[$id]['raw'];

        $this->assertNull($raw['summary']);
        $this->assertEquals(['en' => 'Hello'], json_decode($raw['name'], true));
    }

    public function test_a_translated_column_with_nothing_in_it_does_not_stop_the_ones_after_it(): void
    {
        $id = DB::table('card_items')->insertGetId(['name' => null, 'summary' => '{"en":"Short","ru":"Кратко","fr":"Court"}']);

        $raw = $this->build([$id], 'ru')[$id]['raw'];

        $this->assertNull($raw['name']);
        $this->assertEquals(['en' => 'Short', 'ru' => 'Кратко'], json_decode($raw['summary'], true));
    }

    public function test_a_row_that_is_not_translated_is_kept_as_it_is(): void
    {
        Item::declare(['tags', 'halfOwner']);
        $label = Label::query()->create(['name' => 'red']);
        $owner = Owner::query()->create(['name' => ['en' => 'Ann', 'de' => 'Anna', 'ru' => 'Анна']]);
        $item = $this->item(['owner_id' => $owner->id]);
        $item->tags()->attach($label->id);

        $relations = $this->build([$item->id], 'de')[$item->id]['relations'];

        $this->assertSame(['id' => $label->id, 'name' => 'red'], $relations['tags'][0]['raw']);
        // Names its translated columns but cannot say what is in them.
        $this->assertSame(['id' => $owner->id, 'name' => json_encode(['en' => 'Ann', 'de' => 'Anna', 'ru' => 'Анна'], JSON_UNESCAPED_UNICODE)], $this->normalizeName($relations['halfOwner']['raw']));
    }

    /**
     * The JSON column comes back in the order the database keeps its keys.
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function normalizeName(array $raw): array
    {
        $name = json_decode((string) $raw['name'], true);
        ksort($name);
        $raw['name'] = json_encode(array_replace(['en' => null, 'de' => null, 'ru' => null], $name), JSON_UNESCAPED_UNICODE);

        return $raw;
    }

    public function test_what_a_card_shows_of_the_seo_is_what_the_record_gives(): void
    {
        $with = $this->item(['name' => ['en' => 'Plain name']]);
        $with->updateSeoMeta(['title' => 'Own title']);
        $without = $this->item(['name' => ['en' => 'Plain name']]);

        $cards = $this->build([$with->id, $without->id]);

        $this->assertSame(['title' => 'Own title', 'description' => '', 'keywords' => null, 'og_image' => null], $cards[$with->id]['seo']);
        $this->assertSame(['title' => 'Plain name', 'description' => '', 'keywords' => null, 'og_image' => null], $cards[$without->id]['seo']);
    }

    public function test_a_record_with_no_seo_has_none_on_its_card(): void
    {
        $label = Label::query()->create(['name' => 'red']);

        $card = (new CardBuilder)->build(Label::class, [$label->id], 'en', 1)[$label->id];

        $this->assertSame([], $card['seo']);
        $this->assertSame([], $card['media']);
        $this->assertSame(['id' => $label->id, 'name' => 'red'], $card['raw']);
        $this->assertSame([], $card['relations']);
    }

    public function test_the_seo_methods_of_a_record_may_answer_what_they_like(): void
    {
        Exceptions::fake();
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"x"}']);

        $card = (new CardBuilder)->build(SeoLoose::class, [$id], 'en', 1)[$id];

        $this->assertSame(['title' => '42', 'description' => '7', 'keywords' => 'kw', 'og_image' => 'og.png'], $card['seo']);
        Exceptions::assertNothingReported();
    }

    public function test_a_record_that_knows_only_its_title_has_no_other_seo(): void
    {
        Exceptions::fake();
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"x"}']);

        $card = (new CardBuilder)->build(SeoTitleOnly::class, [$id], 'en', 1)[$id];

        $this->assertSame(['title' => 'Only a title', 'description' => null, 'keywords' => null, 'og_image' => null], $card['seo']);
        Exceptions::assertNothingReported();
    }

    public function test_seo_that_fails_costs_only_the_part_that_failed(): void
    {
        Exceptions::fake();
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"x"}']);

        $card = (new CardBuilder)->build(SeoBroken::class, [$id], 'en', 1)[$id];

        $this->assertSame(['title' => null, 'description' => null, 'keywords' => null, 'og_image' => null], $card['seo']);
        Exceptions::assertReportedCount(4);
    }

    /**
     * @return array<string,array{0:string,1:?string,2:array<int,array{0:string,1:string,2:string}>}>
     */
    public static function pictures(): array
    {
        return [
            'nothing' => ['files', null, []],
            'an empty string' => ['files', '', []],
            'an empty list' => ['files', '[]', []],
            'a JSON list' => ['files', '["a.jpg","b.png"]', [['/storage/a.jpg', 'a.jpg', 'a.jpg'], ['/storage/b.png', 'b.png', 'b.png']]],
            'a JSON list after a space' => ['files', '  ["a.jpg"]', [['/storage/a.jpg', 'a.jpg', 'a.jpg']]],
            'a list that is not JSON is one value' => ['files', '[broken', [['/storage/[broken', '[broken', '[broken']]],
            'one path' => ['files', 'single.jpg', [['/storage/single.jpg', 'single.jpg', 'single.jpg']]],
            'an empty item is left out' => ['files', '["","a.jpg"]', [['/storage/a.jpg', 'a.jpg', 'a.jpg']]],
            'a full address' => ['files', 'https://cdn.example.com/dir/My%20Photo.jpg', [['https://cdn.example.com/dir/My%20Photo.jpg', 'https://cdn.example.com/dir/My%20Photo.jpg', 'My Photo.jpg']]],
            'a path from the web root' => ['files', '/upload/old/x.jpg', [['/upload/old/x.jpg', '/upload/old/x.jpg', 'x.jpg']]],
            'one item with a path and a name' => ['docs', '{"path":"p.pdf","name":"Spec"}', [['/storage/p.pdf', 'p.pdf', 'Spec']]],
            'one item with an address only' => ['docs', '{"url":"https://cdn.example.com/x.pdf"}', [['https://cdn.example.com/x.pdf', 'https://cdn.example.com/x.pdf', 'x.pdf']]],
            'one item with a path and an address' => ['docs', '{"path":"p.pdf","url":"https://cdn.example.com/u.pdf"}', [['https://cdn.example.com/u.pdf', 'p.pdf', 'u.pdf']]],
            'one item with a path and no name' => ['docs', '{"path":"p.pdf"}', [['/storage/p.pdf', 'p.pdf', 'p.pdf']]],
            'a list of items' => ['docs', '[{"path":"a.pdf","name":"A"},{"url":"https://cdn.example.com/b.pdf","name":7}]', [['/storage/a.pdf', 'a.pdf', 'A'], ['https://cdn.example.com/b.pdf', 'https://cdn.example.com/b.pdf', '7']]],
            'a list of paths as an object' => ['docs', '{"a":"x.pdf","b":"y.pdf"}', [['/storage/x.pdf', 'x.pdf', 'x.pdf'], ['/storage/y.pdf', 'y.pdf', 'y.pdf']]],
            'an item that has no address is left out' => ['docs', '[{"name":"nothing"}]', []],
            'a path that is not text' => ['docs', '{"url":"https://cdn.example.com/x.pdf","path":5}', [['https://cdn.example.com/x.pdf', '5', 'x.pdf']]],
        ];
    }

    /**
     * @param  array<int,array{0:string,1:string,2:string}>  $expected  address, path, name
     */
    #[DataProvider('pictures')]
    public function test_pictures_and_files_are_listed_with_their_addresses(string $column, ?string $stored, array $expected): void
    {
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"x"}', $column => $stored]);

        $media = $this->build([$id])[$id]['media'];

        $items = array_map(fn (array $item): array => [$item['url'], $item['path'], $item['name']], $media[$column] ?? []);
        $expectedItems = array_map(fn (array $item): array => [$this->url($item[0]), $item[1], $item[2]], $expected);
        $this->assertSame($expectedItems, $items);
        $this->assertSame($expected === [] ? [] : [$column], array_keys($media));
    }

    public function test_only_the_pictures_and_files_of_the_form_are_pictures_and_files(): void
    {
        Item::declare(null, [TextField::make('name'), 'not a field', ImageField::make('picture'), TextField::make('files'), FileField::make('docs')]);
        $id = DB::table('card_items')->insertGetId(['name' => '{"en":"x"}', 'picture' => 'a.jpg', 'files' => 'b.jpg', 'docs' => '["c.pdf"]']);

        $media = $this->build([$id])[$id]['media'];

        $this->assertSame(['picture', 'docs'], array_keys($media));
    }

    public function test_the_pictures_of_a_class_are_worked_out_once(): void
    {
        Item::declare(null, [CountingImageField::make('picture')]);
        $builder = new CardBuilder;
        $one = $this->item(['picture' => 'a.jpg']);
        CountingImageField::$typeCalls = 0;

        $builder->build(Item::class, [$one->id], 'en', 1);
        $worked = CountingImageField::$typeCalls;
        $builder->build(Item::class, [$one->id], 'en', 1);

        $this->assertGreaterThan(0, $worked);
        $this->assertSame($worked, CountingImageField::$typeCalls);
    }

    public function test_the_fields_of_a_class_are_asked_for_once(): void
    {
        $builder = new CardBuilder;
        $one = $this->item(['picture' => 'a.jpg']);
        $two = $this->item(['picture' => 'b.jpg']);
        Item::$fieldCalls = 0;

        $builder->build(Item::class, [$one->id, $two->id], 'en', 1);
        $builder->build(Item::class, [$one->id], 'en', 1);
        $builder->dependenciesOf(Item::class);
        $builder->referencing($one);

        $this->assertSame(1, Item::$fieldCalls);
    }

    public function test_a_class_without_a_form_has_no_pictures_and_shows_nothing_of_others(): void
    {
        $label = Label::query()->create(['name' => 'red']);
        $builder = new CardBuilder;

        $this->assertSame([], $builder->dependenciesOf(Label::class));
        $this->assertSame([], $builder->referencing($label));
        $this->assertSame([], $builder->relationsOf(Label::class));
    }

    public function test_the_relations_of_a_class_are_what_it_declares_in_a_row(): void
    {
        Item::declare(['first' => 'owner', 'third' => 'tags']);
        $builder = new CardBuilder;

        $this->assertSame(['owner', 'tags'], $builder->relationsOf(Item::class));
    }

    public function test_the_relations_of_a_class_are_asked_for_once(): void
    {
        $builder = new CardBuilder;

        $builder->relationsOf(Item::class);
        $builder->relationsOf(Item::class);
        $builder->dependenciesOf(Item::class);

        $this->assertSame(1, Item::$relationNameCalls);
    }

    public function test_the_classes_a_card_shows_are_those_of_its_relations_and_its_form(): void
    {
        Exceptions::fake();
        Item::declare(['owner', 'link', 'seoMeta', 'subject', 'broken', 'notARelation', 'tags'], []);

        $classes = (new CardBuilder)->dependenciesOf(Item::class);

        // Not its own class, not the SEO meta (a morph relation of its own),
        // not what a morph-to points at, and not what is no relation.
        $this->assertSame([Owner::class, Label::class], $classes);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'no such relation');
        Exceptions::assertReportedCount(1);
    }

    public function test_the_classes_the_form_links_to_count_once_and_not_the_class_itself(): void
    {
        Item::declare(['link', 'owner'], [
            TextField::make('name'),
            'not a field',
            BelongsToField::make('owner_id')->model(Owner::class),
            BelongsToField::make('section_id')->model(Section::class),
            BelongsToField::make('nothing_id'),
            BelongsToManyField::make('related')->model(Item::class),
        ]);

        $this->assertSame([Owner::class, Section::class], (new CardBuilder)->dependenciesOf(Item::class));
    }

    /**
     * @return array<string,array{0:?array<string,mixed>,1:array<int,string>}>
     */
    public static function trees(): array
    {
        return [
            'no rule' => [null, [Owner::class]],
            'a rule without a tree' => [['prefix' => 'items'], [Owner::class]],
            'an empty tree' => [['prefix' => 'items', 'tree' => ''], [Owner::class]],
            'a tree that is no class name' => [['prefix' => 'items', 'tree' => 12], [Owner::class]],
            'a tree of sections' => [['prefix' => 'items', 'parent' => 'section_id', 'tree' => Section::class], [Owner::class, Section::class]],
            'a tree of its own class' => [['prefix' => 'items', 'parent' => 'link_id', 'tree' => Item::class], [Owner::class, Item::class]],
        ];
    }

    /**
     * @param  array<string,mixed>|null  $rule
     * @param  array<int,string>  $expected
     */
    #[DataProvider('trees')]
    public function test_the_tree_the_address_is_made_of_is_shown_too(?array $rule, array $expected): void
    {
        Item::declare(['owner'], []);
        $rule === null || config(['atrium.public_urls.'.Item::class => $rule]);

        $this->assertSame($expected, (new CardBuilder)->dependenciesOf(Item::class));
    }

    public function test_the_classes_a_card_shows_are_worked_out_once(): void
    {
        Item::declare(['owner'], []);
        $builder = new CardBuilder;

        $this->assertSame([Owner::class], $builder->dependenciesOf(Item::class));

        config(['atrium.public_urls.'.Item::class => ['prefix' => 'items', 'parent' => 'section_id', 'tree' => Section::class]]);

        $this->assertSame([Owner::class], $builder->dependenciesOf(Item::class));
        $this->assertSame([Owner::class, Section::class], (new CardBuilder)->dependenciesOf(Item::class));
    }

    /**
     * @return array{0:Item,1:Item,2:Item}
     */
    private function links(): array
    {
        Owner::query()->create(['name' => ['en' => 'Ann']]);
        $target = $this->item(['name' => ['en' => 'Target']]);
        $both = $this->item(['link_id' => $target->id]);
        $both->related()->attach($target->id);
        $linked = $this->item(['link_id' => $target->id]);
        $this->item(['name' => ['en' => 'Stranger']]);

        return [$target, $both, $linked];
    }

    public function test_the_records_that_show_this_one_are_found_through_every_field_that_points_at_it(): void
    {
        [$target, $both, $linked] = $this->links();
        Item::declare(null, [
            TextField::make('name'),
            BelongsToField::make('owner_id')->model(Owner::class),
            BelongsToManyField::make('related')->model(Item::class),
            BelongsToField::make('link_id')->model(Item::class),
        ]);

        $ids = (new CardBuilder)->referencing($target);

        // Each once, in a list.
        $this->assertSame([$both->id, $linked->id], $ids);
    }

    public function test_a_field_that_is_not_a_relation_of_the_class_finds_nothing(): void
    {
        [$target] = $this->links();
        // Owner 1 is also the owner of the first item: a relation that is not
        // a many-to-many must not be asked as one.
        Item::query()->whereKey($target->id + 1)->update(['owner_id' => $target->id]);
        Item::declare(null, [
            BelongsToManyField::make('ghost')->model(Item::class),
            BelongsToManyField::make('owner')->model(Item::class),
        ]);

        $this->assertSame([], (new CardBuilder)->referencing($target));
    }

    public function test_a_field_may_point_through_another_column_and_a_value_it_has_not_set_points_nowhere(): void
    {
        $record = $this->item(['section_id' => 7]);
        $pointing = $this->item(['link_id' => 7]);
        $this->item(['link_id' => null]);
        Item::declare(null, [BelongsToField::make('link_id')->model(Item::class)->valueColumn('section_id')]);

        $this->assertSame([$pointing->id], (new CardBuilder)->referencing($record));

        // The record has no owner: nothing points at it through its absence.
        Item::declare(null, [BelongsToField::make('link_id')->model(Item::class)->valueColumn('owner_id')]);
        $this->assertSame([], (new CardBuilder)->referencing($record));
    }

    public function test_a_field_on_the_key_itself_finds_nothing(): void
    {
        $other = $this->item();
        $record = $this->item(['link_id' => $other->id]);
        Item::declare(null, [BelongsToField::make('id')->model(Item::class)->valueColumn('link_id')]);

        $this->assertSame([], (new CardBuilder)->referencing($record));
    }

    public function test_a_record_that_points_at_itself_does_not_show_itself(): void
    {
        $record = $this->item();
        $record->forceFill(['link_id' => $record->id])->save();
        Item::declare(null, [BelongsToField::make('link_id')->model(Item::class)]);

        $this->assertSame([], (new CardBuilder)->referencing($record));
    }

    /**
     * @return array<string,array{0:BelongsToField}>
     */
    public static function fieldsThatPointNowhere(): array
    {
        return [
            'a many-to-many that is not a relation of the class' => [BelongsToManyField::make('ghost')->model(Item::class)],
            'a many-to-one asked as a many-to-many' => [BelongsToManyField::make('owner')->model(Item::class)],
            'a field the record has no value for' => [BelongsToField::make('link_id')->model(Item::class)->valueColumn('owner_id')],
            'a field on the key itself' => [BelongsToField::make('id')->model(Item::class)->valueColumn('link_id')],
        ];
    }

    #[DataProvider('fieldsThatPointNowhere')]
    public function test_a_field_that_finds_nothing_does_not_stop_the_fields_after_it(BelongsToField $nowhere): void
    {
        $other = $this->item();
        $target = $this->item(['link_id' => $other->id]);
        $linked = $this->item(['link_id' => $target->id]);
        Item::declare(null, [$nowhere, BelongsToField::make('link_id')->model(Item::class)]);

        $this->assertSame([$linked->id], (new CardBuilder)->referencing($target));
    }

    public function test_the_ids_found_through_each_field_are_all_there(): void
    {
        $target = $this->item();
        $related = $this->item();
        $related->related()->attach($target->id);
        $linked = $this->item(['link_id' => $target->id]);
        Item::declare(null, [
            BelongsToManyField::make('related')->model(Item::class),
            BelongsToField::make('link_id')->model(Item::class),
        ]);

        $this->assertSame([$related->id, $linked->id], (new CardBuilder)->referencing($target));
    }

    public function test_the_ids_found_through_two_fields_are_each_there_once(): void
    {
        Owner::query()->create(['name' => ['en' => 'Ann']]);
        $target = $this->item();
        $both = $this->item(['link_id' => $target->id]);
        $both->related()->attach($target->id);
        $linked = $this->item(['link_id' => $target->id]);
        Item::declare(null, [
            BelongsToManyField::make('related')->model(Item::class),
            BelongsToField::make('link_id')->model(Item::class),
        ]);

        $this->assertSame([$both->id, $linked->id], (new CardBuilder)->referencing($target));
    }
}

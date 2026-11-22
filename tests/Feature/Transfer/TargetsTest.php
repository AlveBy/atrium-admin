<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\DisplayField;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\LinkField;
use Atrium\Admin\Fields\MapField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Transfer\RecordWriter;
use Atrium\Admin\Transfer\Target;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a column can fill, and the guess of the mapping from the headers.
 */
final class TargetsTest extends TransferTestCase
{
    public function test_every_field_a_cell_can_fill_in_the_order_of_the_form(): void
    {
        $targets = Targets::for(Parcel::class);

        $this->assertSame(
            ['id', 'article', 'name', 'code', 'price', 'active', 'booking', 'starts_at', 'starts_on', 'label', 'colors', 'features', 'picture', 'photos', 'aisle_id', 'post_id', 'detail_text', 'sort'],
            array_column($targets->options(), 'key'),
        );
        $this->assertSame(['key' => 'id', 'label' => 'ID'], $targets->options()[0]);
        $this->assertSame(['key' => 'article', 'label' => 'Article'], $targets->options()[1]);
        $this->assertSame(['key' => 'aisle_id', 'label' => 'Aisle'], $targets->options()[14]);
        $this->assertSame(array_keys($targets->all()), array_column($targets->options(), 'key'));
    }

    public function test_a_target_knows_its_field(): void
    {
        $targets = Targets::for(Parcel::class);
        $price = $targets->get('price');

        $this->assertSame('price', $price?->key);
        $this->assertSame('Price', $price->label);
        $this->assertSame('price', $price->field?->getName());
        $this->assertNull($price->locale);
        $this->assertNull($price->base);
        $this->assertSame([], $price->synonyms);
        $this->assertNull($price->resolve);
        $this->assertNull($price->export);

        $id = $targets->get('id');
        $this->assertSame('ID', $id?->label);
        $this->assertNull($id->field);
        $this->assertNull($targets->get('ghost'));
        $this->assertSame($id, $targets->all()['id']);
    }

    public function test_a_post_has_the_targets_of_its_own_form(): void
    {
        $this->assertSame(['id', 'name', 'active', 'sort'], array_keys(Targets::for(Post::class)->all()));
    }

    public function test_fields_a_cell_of_text_cannot_fill_are_left_out(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class ? [
            ...$fields,
            DisplayField::make('shown')->label('Shown'),
            BelongsToManyField::make('tags')->label('Tags'),
            TreeField::make('outline')->label('Outline'),
            FileField::make('manual')->label('Manual'),
            LinkField::make('link')->label('Link'),
            MapField::make('place')->label('Place'),
            TreeSelectField::make('aisles')->label('Aisles')->model(ApiAisle::class)->multiple(),
            TreeSelectField::make('second_aisle')->label('Second aisle')->model(ApiAisle::class),
        ] : $fields);

        $keys = array_keys(Targets::for(Parcel::class)->all());

        foreach (['shown', 'tags', 'outline', 'manual', 'link', 'place', 'aisles'] as $left) {
            $this->assertNotContains($left, $keys);
        }

        $this->assertSame(['aisle_id', 'post_id', 'detail_text', 'sort', 'second_aisle'], array_slice($keys, 14));
    }

    public function test_the_tree_is_the_one_a_cell_may_point_into(): void
    {
        $this->assertTrue(Targets::for(Parcel::class)->hasTree());
        $this->assertFalse(Targets::for(Post::class)->hasTree());
    }

    public function test_a_tree_of_several_choices_is_not_a_tree_to_point_into(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, TreeSelectField::make('aisles')->label('Aisles')->model(ApiAisle::class)->multiple()]
            : $fields);

        $this->assertFalse(Targets::for(Post::class)->hasTree());
    }

    public function test_the_keys_are_those_the_module_names_and_has(): void
    {
        $this->assertSame(
            [['key' => 'article', 'label' => 'Article'], ['key' => 'code', 'label' => 'Code'], ['key' => 'id', 'label' => 'ID']],
            Targets::for(Parcel::class)->keys(),
        );
    }

    public function test_a_key_the_form_does_not_have_is_left_out(): void
    {
        $this->assertSame([['key' => 'id', 'label' => 'ID']], Targets::for(Post::class)->keys());

        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, TextField::make('code')->label('Slug')]
            : $fields);

        $this->assertSame([['key' => 'id', 'label' => 'ID'], ['key' => 'code', 'label' => 'Slug']], Targets::for(Post::class)->keys());
    }

    public function test_a_model_that_names_no_keys_is_found_by_its_id_and_code(): void
    {
        $model = new class extends Model
        {
            /**
             * @return array<int,TextField>
             */
            public static function getAdministrationFields(): array
            {
                return [TextField::make('name')->label('Name'), TextField::make('code')->label('Code')];
            }
        };

        $this->assertSame([['key' => 'id', 'label' => 'ID'], ['key' => 'code', 'label' => 'Code']], Targets::for($model::class)->keys());
    }

    public function test_the_key_target_is_the_target_of_the_key(): void
    {
        $targets = Targets::for(Parcel::class);

        $this->assertSame($targets->get('article'), $targets->keyTarget('article'));
        $this->assertSame($targets->get('id'), $targets->keyTarget('id'));
        $this->assertNull($targets->keyTarget('ghost'));
    }

    public function test_the_targets_are_built_for_the_model_and_the_languages_they_are_given(): void
    {
        $targets = new Targets(Parcel::class, new RecordWriter(Parcel::class), app(ContentLanguages::class));

        $this->assertEquals(Targets::for(Parcel::class)->all(), $targets->all());
    }

    public function test_the_targets_are_worked_out_once(): void
    {
        $calls = 0;
        Hook::filter('atrium.module.fields', static function (array $fields) use (&$calls): array {
            $calls++;

            return $fields;
        });
        $targets = Targets::for(Parcel::class);

        $targets->all();
        $targets->get('name');
        $targets->options();
        $targets->keys();
        $targets->hasTree();

        $this->assertSame(1, $calls);
    }

    public function test_headers_are_guessed_from_labels_keys_and_synonyms(): void
    {
        app()->setLocale('de');

        $mapping = Targets::for(Parcel::class)->guess([
            'Title',          // a synonym of the name
            'ARTICLE',        // the label
            'Price',          // the label
            'Cost, $',        // nothing
            'starts_on',      // the key itself
            'Slug',           // a synonym of the code
            'Description',    // the label of the text
            'Published',      // a synonym of the activity
            'Order',          // a synonym of the sort
            'Something else',
            '',
            'ID',
        ]);

        $this->assertSame(['name', 'article', 'price', null, 'starts_on', 'code', 'detail_text', 'active', 'sort', null, null, 'id'], $mapping);
        $this->assertSame('de', app()->getLocale());
    }

    public function test_a_target_takes_one_column_only(): void
    {
        $this->assertSame(['name', null, 'article', null], Targets::for(Parcel::class)->guess(['Name', 'Title', 'Article', 'article']));
    }

    public function test_no_headers_map_to_nothing(): void
    {
        $this->assertSame([], Targets::for(Parcel::class)->guess([]));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function synonyms(): array
    {
        return [
            'title' => ['title', 'name'],
            'наименование' => ['наименование', 'name'],
            'slug' => ['slug', 'code'],
            'url' => ['url', 'code'],
            'чпу' => ['чпу', 'code'],
            'символьный код' => ['символьный код', 'code'],
            'description' => ['description', 'detail_text'],
            'описание' => ['описание', 'detail_text'],
            'order' => ['order', 'sort'],
            'position' => ['position', 'sort'],
            'сортировка' => ['сортировка', 'sort'],
            'enabled' => ['enabled', 'active'],
            'published' => ['published', 'active'],
            'активность' => ['активность', 'active'],
        ];
    }

    #[DataProvider('synonyms')]
    public function test_the_usual_synonyms_of_a_header(string $header, string $target): void
    {
        $this->assertSame([$target], Targets::for(Parcel::class)->guess([$header]));
    }

    public function test_a_header_is_known_by_its_label_in_any_language_of_the_panel(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class
            ? [...$fields, TextField::make('lookup')->label(__('atrium::import.settings.key'))]
            : $fields);
        app()->setLocale('de');

        $targets = Targets::for(Parcel::class);

        $this->assertSame(['lookup'], $targets->guess(['Искать записи по']));
        $this->assertSame(['lookup'], $targets->guess(['Find records by']));
        $this->assertSame(['lookup'], $targets->guess(['искать ЗАПИСИ по']));
        $this->assertSame('de', app()->getLocale());
    }

    public function test_what_comes_first_wins_a_header_two_targets_answer_to(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class ? [
            ...$fields,
            TextField::make('alt_name')->label('Name'),    // a label after the same label
            TextField::make('legacy')->label('Slug'),      // a label before the same synonym
            TextField::make('sku')->label('Price'),        // a label after the same label
        ] : $fields);

        $this->assertSame(['name', 'legacy', 'price'], Targets::for(Parcel::class)->guess(['Name', 'Slug', 'Price']));
    }

    public function test_a_label_comes_before_a_key(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class
            ? [TextField::make('legacy')->label('Code'), ...$fields, TextField::make('other')->label('Sort')]
            : $fields);

        $this->assertSame(['legacy'], Targets::for(Parcel::class)->guess(['code']));
        $this->assertSame(['sort'], Targets::for(Parcel::class)->guess(['Sort']));
        $this->assertSame(['other'], Targets::for(Parcel::class)->guess(['other']));
        $this->assertSame(['code'], Targets::for(Parcel::class)->guess(['Slug']));
    }

    public function test_a_synonym_of_a_field_the_form_does_not_have_maps_to_nothing(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => array_values(array_filter(
            $fields,
            static fn ($field): bool => $field->getName() !== 'name',
        )));

        $this->assertSame([null, 'code'], Targets::for(Parcel::class)->guess(['Title', 'Slug']));
    }

    public function test_a_custom_field_is_a_target_too(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => [...$fields, TextField::make('colour_code')->label('Colour code')]);

        $targets = Targets::for(Parcel::class);

        $this->assertSame(['colour_code'], $targets->guess(['Colour code']));
        $this->assertSame(['colour_code'], $targets->guess(['colour_code']));
        $this->assertSame('colour_code', array_key_last($targets->all()));
    }

    public function test_a_module_adds_a_target_with_its_own_way_of_reading_a_cell(): void
    {
        $resolve = static fn (string $cell, DataImport $import, bool $check): array => ['code' => $cell];
        $export = static fn (Model $record): string => 'x';
        Hook::filter('atrium.import.targets', static function (array $targets, string $model) use ($resolve, $export): array {
            if ($model !== Parcel::class) {
                return $targets;
            }

            $targets['stamp'] = new Target('stamp', 'Stamp', synonyms: ['marker'], resolve: $resolve, export: $export);

            return $targets;
        });

        $targets = Targets::for(Parcel::class);

        $this->assertSame('stamp', array_key_last($targets->all()));
        $this->assertSame(['key' => 'stamp', 'label' => 'Stamp'], $targets->options()[18]);
        $this->assertSame($resolve, $targets->get('stamp')?->resolve);
        $this->assertSame($export, $targets->get('stamp')->export);
        $this->assertNull($targets->get('stamp')->field);
        $this->assertSame(['stamp'], $targets->guess(['Stamp']));
        $this->assertSame(['stamp'], $targets->guess(['stamp']));
        $this->assertSame(['stamp'], $targets->guess(['marker']));
        $this->assertNull(Targets::for(Post::class)->get('stamp'));
    }

    public function test_the_synonyms_of_a_target_come_before_those_of_the_table(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['alt'] = new Target('alt', 'Alternative', synonyms: ['title']);

            return $targets;
        });

        $this->assertSame(['alt', 'name'], Targets::for(Parcel::class)->guess(['Title', 'Name']));
    }

    public function test_a_module_replaces_a_target_with_its_own(): void
    {
        $resolve = static fn (string $cell, DataImport $import, bool $check): array => ['aisle_id' => 7];
        Hook::filter('atrium.import.targets', static function (array $targets) use ($resolve): array {
            $targets['aisle_id'] = new Target('aisle_id', 'Aisle', null, null, null, ['category'], $resolve);

            return $targets;
        });

        $targets = Targets::for(Parcel::class);

        $this->assertSame($resolve, $targets->get('aisle_id')?->resolve);
        $this->assertSame(['aisle_id'], $targets->guess(['Category']));
        $this->assertSame(['aisle_id'], $targets->guess(['Aisle']));
        $this->assertSame(14, array_search('aisle_id', array_keys($targets->all()), true));
    }

    public function test_a_target_may_answer_to_more_headers(): void
    {
        $resolve = static fn (string $cell, DataImport $import, bool $check): array => [];
        $export = static fn (Model $record): string => '';
        $target = new Target('k', 'K', Targets::for(Parcel::class)->get('price')?->field, 'ru', 'Base', ['one'], $resolve, $export);

        $more = $target->withSynonyms(['two', 'three']);

        $this->assertNotSame($target, $more);
        $this->assertSame(['one'], $target->synonyms);
        $this->assertSame(['one', 'two', 'three'], $more->synonyms);
        $this->assertSame(['k', 'K', 'ru', 'Base'], [$more->key, $more->label, $more->locale, $more->base]);
        $this->assertSame($target->field, $more->field);
        $this->assertSame($resolve, $more->resolve);
        $this->assertSame($export, $more->export);
    }

    public function test_a_module_takes_a_target_away(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            unset($targets['sort']);

            return $targets;
        });

        $targets = Targets::for(Parcel::class);

        $this->assertNull($targets->get('sort'));
        $this->assertSame([null], $targets->guess(['Sort']));
        $this->assertSame([null], $targets->guess(['Order']));
    }

    public function test_a_field_kept_per_language_takes_a_column_per_language(): void
    {
        $this->addRussian();

        $targets = Targets::for(Parcel::class);

        $this->assertNull($targets->get('name'));
        $this->assertSame(['name.en', 'name.ru'], array_values(array_filter(array_keys($targets->all()), static fn (string $key): bool => str_starts_with($key, 'name'))));
        $english = $targets->get('name.en');
        $this->assertSame(['name.en', 'Name (English)', 'Name', 'en'], [$english?->key, $english->label, $english->base, $english->locale]);
        $this->assertSame('name', $english->field?->getName());
        $russian = $targets->get('name.ru');
        $this->assertSame(['name.ru', 'Name (Русский)', 'Name', 'ru'], [$russian?->key, $russian->label, $russian->base, $russian->locale]);
        $this->assertSame('name', $russian->field?->getName());

        // "Name" alone is the default language's; the other one is named with its language.
        $this->assertSame(['name.en'], $targets->guess(['Name']));
        $this->assertSame(['name.en'], $targets->guess(['Name (English)']));
        $this->assertSame(['name.ru'], $targets->guess(['Name (Русский)']));
        $this->assertSame([null], $targets->guess(['Name (Deutsch)']));
        $this->assertSame(['name.en', null], $targets->guess(['Name', 'Name']));
        $this->assertSame(['name.en'], $targets->guess(['name.en']));
        $this->assertSame(['name.ru'], $targets->guess(['name.ru']));
    }

    public function test_the_synonyms_of_a_field_kept_per_language_are_those_of_the_default_language(): void
    {
        $this->addRussian();

        $this->assertSame(['name.en'], Targets::for(Parcel::class)->guess(['Title']));
        $this->assertSame(['name.en'], Targets::for(Parcel::class)->guess(['наименование']));
    }

    public function test_a_label_in_another_language_of_the_panel_finds_the_default_languages_field(): void
    {
        $this->addRussian();
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class
            ? [...$fields, TextField::make('lookup')->label(__('atrium::import.settings.key'))]
            : $fields);

        $this->assertSame(['lookup'], Targets::for(Parcel::class)->guess(['Искать записи по']));
    }

    public function test_the_first_of_two_fields_kept_per_language_with_one_label_takes_the_bare_label(): void
    {
        $this->addRussian();
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Parcel::class
            ? [...$fields, TextField::make('alt_name')->label('Name')]
            : $fields);
        Hook::filter('atrium.module.translatable', static fn (array $names, string $model): array => $model === Parcel::class ? [...$names, 'alt_name'] : $names);

        $this->assertSame(['name.en'], Targets::for(Parcel::class)->guess(['Name']));
        $this->assertSame(['name.en'], Targets::for(Parcel::class)->guess(['Name (English)']));
        $this->assertSame(['alt_name.en', 'alt_name.ru'], array_values(array_filter(array_keys(Targets::for(Parcel::class)->all()), static fn (string $key): bool => str_starts_with($key, 'alt_name'))));
    }

    public function test_one_language_needs_no_language_in_the_name(): void
    {
        $targets = Targets::for(Parcel::class);

        $this->assertSame('Name', $targets->get('name')?->label);
        $this->assertNull($targets->get('name')->locale);
        $this->assertNull($targets->get('name.en'));
        $this->assertSame(['name'], $targets->guess(['Name']));
    }

    public function test_a_field_kept_per_language_is_a_key_in_the_default_language(): void
    {
        $this->addRussian();
        $targets = Targets::for(Tale::class);

        $this->assertSame(['key' => 'code', 'label' => 'Code (English)'], $targets->keys()[0]);
        $this->assertSame([['key' => 'code', 'label' => 'Code (English)'], ['key' => 'id', 'label' => 'ID']], $targets->keys());
        $this->assertSame($targets->get('code.en'), $targets->keyTarget('code'));
        $this->assertSame('code.en', $targets->keyTarget('code')?->key);
        $this->assertNull($targets->keyTarget('title'));
    }

    public function test_one_language_is_a_key_as_it_is(): void
    {
        $targets = Targets::for(Tale::class);

        $this->assertSame([['key' => 'code', 'label' => 'Code'], ['key' => 'id', 'label' => 'ID']], $targets->keys());
        $this->assertSame('code', $targets->keyTarget('code')?->key);
    }

    private function addRussian(): void
    {
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'active' => true, 'is_default' => false, 'sort' => 2]);
        app(ContentLanguages::class)->flush();
    }
}

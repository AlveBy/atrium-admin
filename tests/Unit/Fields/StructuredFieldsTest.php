<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\MapField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Models\Site;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Slugs, map points and lists of values.
 */
final class StructuredFieldsTest extends FieldTestCase
{
    public function test_a_slug_is_made_from_the_name_and_previewed_on_the_current_site(): void
    {
        $field = SlugField::make('code');
        $array = $field->toArray();

        $this->assertSame('slug', $field->getType());
        $this->assertSame('name', $array['fromField']);
        $this->assertTrue($array['autoGenerate']);
        $this->assertTrue($array['showPreview']);
        $this->assertSame(Site::query()->firstOrFail()->url(), $array['baseUrl']);
        $this->assertNull($array['publicPrefix']);
        $this->assertNull($array['parentField']);
        $this->assertSame([], $array['parentPaths']);
        $this->assertSame(['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'], $field->getValidationRules());
    }

    public function test_a_slug_field_takes_its_source_and_preview_address(): void
    {
        $array = SlugField::make('code')->from('title')->autoGenerate(false)->showPreview(false)->baseUrl('https://shop.example')->toArray();

        $this->assertSame('title', $array['fromField']);
        $this->assertFalse($array['autoGenerate']);
        $this->assertFalse($array['showPreview']);
        $this->assertSame('https://shop.example', $array['baseUrl']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function slugs(): array
    {
        return [
            'plain' => ['garden-chair', true],
            'digits' => ['2026', true],
            'one character' => ['a', true],
            'longest allowed' => [str_repeat('a', 255), true],
            'one too long' => [str_repeat('a', 256), false],
            'empty, the field is optional' => ['', true],
            'upper case' => ['Garden', false],
            'a space' => ['garden chair', false],
            'an underscore' => ['garden_chair', false],
            'a slash' => ['shop/chair', false],
            'going up a folder' => ['../admin', false],
            'Cyrillic' => ['стул', false],
            'Chinese' => ['椅子', false],
            'emoji' => ['chair-🪑', false],
            'a script' => ['<script>', false],
            'an array' => [['garden'], false],
        ];
    }

    #[DataProvider('slugs')]
    public function test_a_slug_takes_only_lower_case_latin_letters_digits_and_dashes(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(SlugField::make('code'), $value));
    }

    public function test_a_map_shows_yandex_around_moscow_by_default(): void
    {
        config(['atrium.maps.yandex_api_key' => 'yandex-key', 'atrium.maps.google_api_key' => 'google-key']);

        $field = MapField::make('location');
        $array = $field->toArray();

        $this->assertSame('map', $field->getType());
        $this->assertSame('yandex', $array['mapType']);
        $this->assertSame(55.7558, $array['defaultLat']);
        $this->assertSame(37.6173, $array['defaultLng']);
        $this->assertSame(10, $array['defaultZoom']);
        $this->assertTrue($array['searchEnabled']);
        $this->assertSame('yandex-key', $array['apiKey']);
        $this->assertSame(['nullable'], $field->getValidationRules());
    }

    /**
     * @return array<string, array{\Closure(MapField):MapField, string, string|null}>
     */
    public static function providers(): array
    {
        return [
            'google' => [fn (MapField $f): MapField => $f->google(), 'google', 'google-key'],
            'yandex again' => [fn (MapField $f): MapField => $f->google()->yandex(), 'yandex', 'yandex-key'],
            'by name' => [fn (MapField $f): MapField => $f->mapType('google'), 'google', 'google-key'],
            'an unknown one gets the google key' => [fn (MapField $f): MapField => $f->mapType('osm'), 'osm', 'google-key'],
        ];
    }

    #[DataProvider('providers')]
    public function test_the_map_gets_the_key_of_its_provider(\Closure $configure, string $type, ?string $key): void
    {
        config(['atrium.maps.yandex_api_key' => 'yandex-key', 'atrium.maps.google_api_key' => 'google-key']);

        $array = $configure(MapField::make('location'))->toArray();

        $this->assertSame($type, $array['mapType']);
        $this->assertSame($key, $array['apiKey']);
    }

    public function test_a_map_takes_its_centre_zoom_and_search(): void
    {
        $array = MapField::make('location')->defaultCenter(53.9, 27.5667)->defaultZoom(15)->searchEnabled(false)->toArray();

        $this->assertSame(53.9, $array['defaultLat']);
        $this->assertSame(27.5667, $array['defaultLng']);
        $this->assertSame(15, $array['defaultZoom']);
        $this->assertFalse($array['searchEnabled']);
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function points(): array
    {
        return [
            'JSON' => ['{"lat":53.9,"lng":27.56,"address":"Минск"}', ['lat' => 53.9, 'lng' => 27.56, 'address' => 'Минск']],
            'already an array' => [['lat' => 1.5, 'lng' => 2.5], ['lat' => 1.5, 'lng' => 2.5]],
            'null' => [null, null],
            'a Bitrix comma string is not JSON' => ['61.79862,34.368981', null],
            'empty string' => ['', null],
        ];
    }

    #[DataProvider('points')]
    public function test_a_map_point_is_saved_as_an_array(mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, MapField::make('location')->transformValue($value));
    }

    public function test_a_map_takes_both_json_and_a_bitrix_comma_string(): void
    {
        $field = MapField::make('location');

        $this->assertTrue($this->passes($field, '61.79862,34.368981'));
        $this->assertTrue($this->passes($field, ['lat' => 1, 'lng' => 2]));
        $this->assertFalse($this->passes($field->required(), null));
    }

    public function test_a_repeatable_field_is_an_unlimited_list_of_text(): void
    {
        $field = RepeatableField::make('phones');
        $array = $field->toArray();

        $this->assertSame('repeatable', $field->getType());
        $this->assertSame('text', $array['itemType']);
        $this->assertSame(0, $array['maxItems']);
        $this->assertSame(0, $array['minItems']);
        $this->assertSame('Add', $array['addLabel']);
        $this->assertSame('', $array['itemLabel']);
        $this->assertSame(['text', 'html', 'wysiwyg'], $array['itemModes']);
        $this->assertSame(['bold', 'italic', 'underline', 'strike', 'color', 'link', 'bulletList', 'orderedList', 'align', 'heading', 'fontSize', 'image', 'file', 'table'], $array['itemToolbar']);
        $this->assertSame(['nullable', 'array'], $field->getValidationRules());
    }

    public function test_the_add_button_speaks_every_language(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            $label = RepeatableField::make('phones')->toArray()['addLabel'];

            $this->assertSame(__('atrium::common.add'), $label, $locale);
            $this->assertStringStartsNotWith('atrium::', $label, $locale);
        }

        $this->assertSame('Добавить дату', RepeatableField::make('dates')->addLabel('Добавить дату')->toArray()['addLabel']);
    }

    public function test_a_repeatable_field_takes_its_item_settings(): void
    {
        $array = RepeatableField::make('days')
            ->itemType('content')
            ->itemLabel('День #')
            ->itemModes(['html'])
            ->itemToolbar(['bold'])
            ->toArray();

        $this->assertSame('content', $array['itemType']);
        $this->assertSame('День #', $array['itemLabel']);
        $this->assertSame(['html'], $array['itemModes']);
        $this->assertSame(['bold'], $array['itemToolbar']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function lists(): array
    {
        return [
            'the fewest allowed' => [['a', 'b'], true],
            'the most allowed' => [['a', 'b', 'c'], true],
            'one too few' => [['a'], false],
            'one too many' => [['a', 'b', 'c', 'd'], false],
            'empty list' => [[], false],
            'empty, the field is optional' => [null, true],
            'a string, not a list' => ['a,b', false],
        ];
    }

    #[DataProvider('lists')]
    public function test_a_list_with_too_few_or_too_many_items_is_refused(mixed $value, bool $passes): void
    {
        $field = RepeatableField::make('dates')->minItems(2)->maxItems(3);

        $this->assertSame(2, $field->toArray()['minItems']);
        $this->assertSame(3, $field->toArray()['maxItems']);
        $this->assertSame(['nullable', 'array', 'max:3', 'min:2'], $field->getValidationRules());
        $this->assertSame($passes, $this->passes($field, $value));
    }

    public function test_only_a_lower_or_only_an_upper_count_can_be_set(): void
    {
        $this->assertSame(['nullable', 'array', 'min:1'], RepeatableField::make('a')->minItems(1)->getValidationRules());
        $this->assertSame(['nullable', 'array', 'max:1'], RepeatableField::make('a')->maxItems(1)->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, array<int, mixed>}>
     */
    public static function saved(): array
    {
        return [
            'kept in order' => [['b', 'a'], ['b', 'a']],
            'empty items are dropped and the list renumbered' => [[0 => 'a', 1 => '', 2 => null, 3 => 'b'], ['a', 'b']],
            'zero is kept' => [['0', 0, false], ['0', 0, false]],
            'only spaces are kept' => [[' '], [' ']],
            'keys are dropped' => [['x' => 'a', 'y' => 'b'], ['a', 'b']],
            'all empty' => [['', null], []],
            'null' => [null, []],
            'a string' => ['a', []],
            'Unicode' => [['Минск', '東京', 'القاهرة', '🪑'], ['Минск', '東京', 'القاهرة', '🪑']],
        ];
    }

    /**
     * @param  array<int, mixed>  $expected
     */
    #[DataProvider('saved')]
    public function test_a_list_is_saved_without_empty_items(mixed $value, array $expected): void
    {
        $this->assertSame($expected, RepeatableField::make('phones')->transformValue($value));
    }
}

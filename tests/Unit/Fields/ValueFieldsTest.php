<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\ColorField;
use Atrium\Admin\Fields\NumberField;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Numbers, checkboxes and colours.
 */
final class ValueFieldsTest extends FieldTestCase
{
    public function test_a_number_field_takes_whole_numbers_without_bounds(): void
    {
        $field = NumberField::make('sort');

        $this->assertSame('number', $field->getType());
        $this->assertSame(['min' => null, 'max' => null, 'step' => 1.0, 'decimals' => 0, 'prefix' => null, 'suffix' => null], array_intersect_key($field->toArray(), array_flip(['min', 'max', 'step', 'decimals', 'prefix', 'suffix'])));
        $this->assertSame(['nullable', 'numeric'], $field->getValidationRules());
    }

    public function test_a_number_field_takes_its_bounds_step_and_signs(): void
    {
        $field = NumberField::make('weight')->min(-5)->max(2.5)->step(0.5)->prefix('≈')->suffix('кг');
        $array = $field->toArray();

        $this->assertSame(-5.0, $array['min']);
        $this->assertSame(2.5, $array['max']);
        $this->assertSame(0.5, $array['step']);
        $this->assertSame('≈', $array['prefix']);
        $this->assertSame('кг', $array['suffix']);
        $this->assertSame(['nullable', 'numeric', 'min:-5', 'max:2.5'], $field->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function bounded(): array
    {
        return [
            'inside' => [5, true],
            'the lowest allowed' => [0, true],
            'the highest allowed' => [10, true],
            'just under the lowest' => [-0.01, false],
            'just over the highest' => [10.01, false],
            'negative' => [-1, false],
            'a number as a string' => ['7', true],
            'a decimal as a string' => ['7.5', true],
            'empty, the field is optional' => [null, true],
            'not a number' => ['seven', false],
            'an array' => [[5], false],
            'the largest integer' => [PHP_INT_MAX, false],
        ];
    }

    #[DataProvider('bounded')]
    public function test_a_number_outside_the_bounds_is_refused(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(NumberField::make('rating')->min(0)->max(10), $value));
    }

    public function test_only_a_lower_or_only_an_upper_bound_can_be_set(): void
    {
        $this->assertSame(['nullable', 'numeric', 'min:1'], NumberField::make('a')->min(1)->getValidationRules());
        $this->assertSame(['nullable', 'numeric', 'max:1'], NumberField::make('a')->max(1)->getValidationRules());
        $this->assertTrue($this->passes(NumberField::make('a')->min(1), PHP_INT_MAX));
        $this->assertTrue($this->passes(NumberField::make('a')->max(1), -1000000));
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function decimals(): array
    {
        return [
            'none' => [0, 1.0],
            'one' => [1, 0.1],
            'two' => [2, 0.01],
            'three' => [3, 0.001],
        ];
    }

    #[DataProvider('decimals')]
    public function test_decimal_places_set_the_step(int $decimals, float $step): void
    {
        $array = NumberField::make('price')->decimals($decimals)->toArray();

        $this->assertSame($decimals, $array['decimals']);
        $this->assertEqualsWithDelta($step, $array['step'], 1e-12);
    }

    /**
     * @return array<string, array{int, mixed, float|null}>
     */
    public static function saved(): array
    {
        return [
            'null' => [0, null, null],
            'empty string' => [0, '', null],
            'zero is kept' => [0, '0', 0.0],
            'whole number' => [0, '42', 42.0],
            'rounded to whole' => [0, '42.6', 43.0],
            'rounded down to whole' => [0, '42.4', 42.0],
            'rounded to two places' => [2, '19.999', 20.0],
            'kept at two places' => [2, '19.95', 19.95],
            'rounded half up' => [2, '1.005', 1.01],
            'negative' => [1, '-3.14', -3.1],
            'an integer' => [2, 7, 7.0],
        ];
    }

    #[DataProvider('saved')]
    public function test_a_number_is_saved_rounded_to_its_decimal_places(int $decimals, mixed $value, ?float $expected): void
    {
        $this->assertSame($expected, NumberField::make('price')->decimals($decimals)->transformValue($value));
    }

    public function test_a_money_field_has_two_places_a_currency_and_no_negative_amounts(): void
    {
        $field = NumberField::make('price')->money();
        $array = $field->toArray();

        $this->assertSame(2, $array['decimals']);
        $this->assertSame('₽', $array['suffix']);
        $this->assertSame(0.0, $array['min']);
        $this->assertSame(['nullable', 'numeric', 'min:0'], $field->getValidationRules());
        $this->assertSame(10.5, $field->transformValue('10.499'));
        $this->assertFalse($this->passes($field, -1));
        $this->assertSame('€', NumberField::make('price')->money('€')->toArray()['suffix']);
    }

    public function test_a_percentage_lies_between_zero_and_a_hundred(): void
    {
        $field = NumberField::make('discount')->percentage();
        $array = $field->toArray();

        $this->assertSame('%', $array['suffix']);
        $this->assertSame(['nullable', 'numeric', 'min:0', 'max:100'], $field->getValidationRules());
        $this->assertTrue($this->passes($field, 100));
        $this->assertFalse($this->passes($field, 101));
        $this->assertFalse($this->passes($field, -1));
    }

    public function test_a_checkbox_stores_a_boolean(): void
    {
        $field = CheckboxField::make('active');

        $this->assertSame('checkbox', $field->getType());
        $this->assertSame(['trueLabel' => null, 'falseLabel' => null, 'checkedValue' => null], array_intersect_key($field->toArray(), array_flip(['trueLabel', 'falseLabel', 'checkedValue'])));
        $this->assertSame(['nullable', 'boolean'], $field->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, bool, bool}>
     */
    public static function ticks(): array
    {
        return [
            'true' => [true, true, true],
            'false' => [false, true, false],
            'one' => [1, true, true],
            'zero' => [0, true, false],
            'one as a string' => ['1', true, true],
            'zero as a string' => ['0', true, false],
            'left untouched' => [null, true, false],
            'a word' => ['yes', false, true],
            'an array' => [[1], false, true],
        ];
    }

    #[DataProvider('ticks')]
    public function test_a_checkbox_takes_only_a_boolean_and_saves_it(mixed $value, bool $passes, bool $saved): void
    {
        $field = CheckboxField::make('active');

        $this->assertSame($passes, $this->passes($field, $value));
        $this->assertSame($saved, $field->transformValue($value));
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function optionCodes(): array
    {
        return [
            'ticked' => [true, 'YES'],
            'ticked, sent as one' => ['1', 'YES'],
            'ticked, sent as the code' => ['YES', 'YES'],
            'not ticked' => [false, null],
            'not ticked, sent as zero' => ['0', null],
            'left untouched' => [null, null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('optionCodes')]
    public function test_a_checkbox_with_a_bitrix_option_code_stores_the_code_or_nothing(mixed $value, ?string $saved): void
    {
        $field = CheckboxField::make('show_on_home')->checkedValue('YES');

        $this->assertSame('YES', $field->toArray()['checkedValue']);
        $this->assertSame(['nullable'], $field->getValidationRules(), 'the code is not a boolean');
        $this->assertTrue($this->passes($field, $value));
        $this->assertSame($saved, $field->transformValue($value));
    }

    public function test_a_checkbox_shows_its_labels(): void
    {
        $one = CheckboxField::make('a')->trueLabel('Вкл')->falseLabel('Выкл')->toArray();
        $both = CheckboxField::make('a')->labels('Да', 'Нет')->toArray();

        $this->assertSame(['Вкл', 'Выкл'], [$one['trueLabel'], $one['falseLabel']]);
        $this->assertSame(['Да', 'Нет'], [$both['trueLabel'], $both['falseLabel']]);
    }

    public function test_a_colour_field_takes_hex_with_any_colour(): void
    {
        $field = ColorField::make('color');
        $array = $field->toArray();

        $this->assertSame('color', $field->getType());
        $this->assertSame([], $array['presetColors']);
        $this->assertTrue($array['allowCustom']);
        $this->assertSame('hex', $array['format']);
        $this->assertSame(['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'], $field->getValidationRules());
    }

    public function test_a_colour_field_takes_presets_and_a_format(): void
    {
        $field = ColorField::make('color')->presetColors(['#fff', '#000'])->allowCustom(false)->format('rgb');
        $array = $field->toArray();

        $this->assertSame(['#fff', '#000'], $array['presetColors']);
        $this->assertFalse($array['allowCustom']);
        $this->assertSame('rgb', $array['format']);
        $this->assertSame(['nullable', 'string'], $field->getValidationRules(), 'only hex is checked');
        $this->assertTrue($this->passes($field, 'rgb(1, 2, 3)'));
        $this->assertFalse($this->passes($field, ['rgb']));
        $this->assertTrue(ColorField::make('c')->allowCustom(false)->allowCustom()->toArray()['allowCustom']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function colours(): array
    {
        return [
            'six digits' => ['#1a2B3c', true],
            'three digits' => ['#abc', true],
            'upper case' => ['#ABCDEF', true],
            'empty, the field is optional' => [null, true],
            'no hash' => ['1a2b3c', false],
            'four digits' => ['#abcd', false],
            'seven digits' => ['#1234567', false],
            'not hex' => ['#ggg', false],
            'a name' => ['red', false],
            'rgb' => ['rgb(0,0,0)', false],
            'an array' => [['#abc'], false],
            'a script' => ['#abc"><script>', false],
        ];
    }

    #[DataProvider('colours')]
    public function test_a_hex_colour_field_refuses_what_is_not_a_hex_colour(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(ColorField::make('color'), $value));
    }
}

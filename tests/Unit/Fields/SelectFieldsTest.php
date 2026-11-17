<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Priority;
use Atrium\Admin\Tests\Fixtures\Size;
use Atrium\Admin\Tests\Fixtures\Weekday;
use PHPUnit\Framework\Attributes\DataProvider;

final class SelectFieldsTest extends FieldTestCase
{
    public function test_a_select_without_options_takes_any_value(): void
    {
        $field = SelectField::make('template');
        $array = $field->toArray();

        $this->assertSame('select', $field->getType());
        $this->assertSame([], $array['options']);
        $this->assertFalse($array['searchable']);
        $this->assertTrue($array['clearable']);
        $this->assertSame('label', $array['optionLabel']);
        $this->assertSame('value', $array['optionValue']);
        $this->assertSame(['nullable'], $field->getValidationRules());
        $this->assertTrue($this->passes($field, 'anything'));
    }

    /**
     * @return array<string, array{array<mixed>, array<int, array<string, mixed>>}>
     */
    public static function optionLists(): array
    {
        return [
            'value => label' => [['s' => 'Small', 'l' => 'Large'], [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]],
            'a plain list is numbered' => [['Small', 'Large'], [['value' => 0, 'label' => 'Small'], ['value' => 1, 'label' => 'Large']]],
            'ready-made options are kept' => [[['value' => 's', 'label' => 'Small', 'color' => 'red']], [['value' => 's', 'label' => 'Small', 'color' => 'red']]],
            'both kinds mixed' => [['s' => 'Small', ['value' => 'l', 'label' => 'Large']], [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]],
            'Unicode labels' => [['ru' => 'Русский', 'zh' => '中文', 'ar' => 'العربية'], [['value' => 'ru', 'label' => 'Русский'], ['value' => 'zh', 'label' => '中文'], ['value' => 'ar', 'label' => 'العربية']]],
            'none' => [[], []],
        ];
    }

    /**
     * @param  array<mixed>  $given
     * @param  array<int, array<string, mixed>>  $expected
     */
    #[DataProvider('optionLists')]
    public function test_options_are_turned_into_value_and_label_pairs(array $given, array $expected): void
    {
        $this->assertSame($expected, SelectField::make('size')->options($given)->toArray()['options']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function choices(): array
    {
        return [
            'an option' => ['default', true],
            'another option' => ['contacts', true],
            'an option with a comma' => ['a,b', true],
            'half of the option with a comma' => ['a', false],
            'an option with a pipe' => ['x|y', true],
            'not an option' => ['price', false],
            'empty, the field is optional' => [null, true],
            'an array' => [['default'], false],
        ];
    }

    #[DataProvider('choices')]
    public function test_a_value_that_is_not_an_option_is_refused(mixed $value, bool $passes): void
    {
        $field = SelectField::make('template')->options(['default' => 'Default', 'contacts' => 'Contacts', 'a,b' => 'Comma', 'x|y' => 'Pipe']);

        $this->assertSame($passes, $this->passes($field, $value));
    }

    public function test_a_value_stored_before_the_option_was_removed_can_still_be_saved(): void
    {
        $field = SelectField::make('name')->options(['default' => 'Default', 'contacts' => 'Contacts']);
        $record = new Post(['name' => 'price']);

        $this->assertTrue($this->passes($field, 'price', FieldContext::edit($record, 'price', true)), 'the stored value stays');
        $this->assertTrue($this->passes($field, 'default', FieldContext::edit($record, 'default', true)));
        $this->assertFalse($this->passes($field, 'other', FieldContext::edit($record, 'other', true)), 'a new value outside the list is still refused');
        $this->assertFalse($this->passes($field, 'price', FieldContext::create('price', true)), 'nothing is stored on create');
        $this->assertFalse($this->passes($field, 'price'));
    }

    public function test_the_stored_value_is_read_from_the_column_of_the_field(): void
    {
        $field = SelectField::make('template')->column('name')->options(['default' => 'Default']);

        $this->assertTrue($this->passes($field, 'price', FieldContext::edit(new Post(['name' => 'price']))));
    }

    public function test_a_stored_option_is_not_listed_twice(): void
    {
        $field = SelectField::make('name')->options(['default' => 'Default', 'contacts' => 'Contacts']);
        $rules = $field->getValidationRules(FieldContext::edit(new Post(['name' => 'default'])));

        $this->assertSame('in:"default","contacts"', (string) end($rules));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyStored(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'an array' => [['price']],
        ];
    }

    #[DataProvider('emptyStored')]
    public function test_an_empty_or_odd_stored_value_does_not_widen_the_options(mixed $stored): void
    {
        $field = SelectField::make('name')->options(['default' => 'Default']);
        $record = new Post;
        $record->setRawAttributes(['name' => $stored]);

        $rules = $field->getValidationRules(FieldContext::edit($record));

        $this->assertSame('in:"default"', (string) end($rules));
        $this->assertFalse($this->passes($field, 'price', FieldContext::edit($record)));
    }

    public function test_a_stored_number_widens_the_options(): void
    {
        $field = SelectField::make('sort')->options([10 => 'Ten']);
        $rules = $field->getValidationRules(FieldContext::edit(new Post(['sort' => 500])));

        $this->assertSame('in:"10","500"', (string) end($rules));
    }

    public function test_a_select_takes_its_display_settings(): void
    {
        $array = SelectField::make('a')->searchable()->clearable(false)->optionLabel('title')->optionValue('id')->toArray();

        $this->assertTrue($array['searchable']);
        $this->assertFalse($array['clearable']);
        $this->assertSame('title', $array['optionLabel']);
        $this->assertSame('id', $array['optionValue']);
        $this->assertFalse(SelectField::make('a')->searchable()->searchable(false)->toArray()['searchable']);
    }

    public function test_options_from_a_backed_enum_use_its_values_and_labels(): void
    {
        $field = SelectField::make('size')->fromEnum(Size::class);

        $this->assertSame([['value' => 's', 'label' => 'Маленький'], ['value' => 'l', 'label' => 'Большой']], $field->toArray()['options']);
        $this->assertTrue($this->passes($field, 's'));
        $this->assertFalse($this->passes($field, 'm'));
    }

    public function test_options_from_an_enum_without_labels_use_the_case_names(): void
    {
        $this->assertSame([['value' => 1, 'label' => 'Low'], ['value' => 2, 'label' => 'High']], SelectField::make('priority')->fromEnum(Priority::class)->toArray()['options']);
        $this->assertSame([['value' => 'Monday', 'label' => 'Monday'], ['value' => 'Tuesday', 'label' => 'Tuesday']], SelectField::make('day')->fromEnum(Weekday::class)->toArray()['options']);
    }

    public function test_a_class_that_is_not_an_enum_leaves_the_options_alone(): void
    {
        $field = SelectField::make('size')->options(['s' => 'Small'])->fromEnum(Post::class)->fromEnum('No\\Such\\Enum');

        $this->assertSame([['value' => 's', 'label' => 'Small']], $field->toArray()['options']);
    }

    public function test_options_from_a_model_are_described_for_the_form(): void
    {
        $default = SelectField::make('post_id')->fromModel(Post::class)->toArray()['attributes'];
        $custom = SelectField::make('post_id')->withAttributes(['data-x' => 1])->fromModel(Post::class, 'title', 'uuid')->toArray()['attributes'];

        $this->assertSame(['modelClass' => Post::class, 'labelColumn' => 'name', 'valueColumn' => 'id'], $default);
        $this->assertSame(['data-x' => 1, 'modelClass' => Post::class, 'labelColumn' => 'title', 'valueColumn' => 'uuid'], $custom);
    }

    public function test_a_multi_select_is_an_unlimited_list(): void
    {
        $field = MultiSelectField::make('tags')->options(['a' => 'A', 'b' => 'B']);
        $array = $field->toArray();

        $this->assertSame('multiSelect', $field->getType());
        $this->assertTrue($array['multiple']);
        $this->assertSame(0, $array['maxItems']);
        $this->assertSame(0, $array['minItems']);
        $this->assertSame([['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']], $array['options']);
        $this->assertSame(['nullable', 'array'], $field->getValidationRules(), 'no in: rule for a list');
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function selections(): array
    {
        return [
            'the fewest allowed' => [['a'], true],
            'the most allowed' => [['a', 'b'], true],
            'none' => [[], false],
            'one too many' => [['a', 'b', 'c'], false],
            'empty, the field is optional' => [null, true],
            'one value, not a list' => ['a', false],
        ];
    }

    #[DataProvider('selections')]
    public function test_a_multi_select_refuses_too_few_or_too_many_values(mixed $value, bool $passes): void
    {
        $field = MultiSelectField::make('tags')->minItems(1)->maxItems(2);

        $this->assertSame(1, $field->toArray()['minItems']);
        $this->assertSame(2, $field->toArray()['maxItems']);
        $this->assertSame(['nullable', 'array', 'min:1', 'max:2'], $field->getValidationRules());
        $this->assertSame($passes, $this->passes($field, $value));
    }

    public function test_a_multi_select_keeps_explicit_rules(): void
    {
        $this->assertSame(['required', 'distinct', 'array'], MultiSelectField::make('tags')->required()->rules('distinct')->getValidationRules());
        $this->assertSame(['nullable', 'array', 'max:3'], MultiSelectField::make('tags')->maxItems(3)->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, array<mixed>}>
     */
    public static function saved(): array
    {
        return [
            'a list' => [['a', 'b'], ['a', 'b']],
            'one value' => ['a', ['a']],
            'zero' => [0, [0]],
            'null' => [null, []],
            'an empty list' => [[], []],
        ];
    }

    /**
     * @param  array<mixed>  $expected
     */
    #[DataProvider('saved')]
    public function test_a_multi_select_is_always_saved_as_a_list(mixed $value, array $expected): void
    {
        $this->assertSame($expected, MultiSelectField::make('tags')->transformValue($value));
    }
}

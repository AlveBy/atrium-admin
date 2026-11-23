<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\FormBuilderField;
use Closure;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The builder of a form's fields: what it gives the page and which lists of
 * fields it lets through.
 */
final class FormBuilderFieldTest extends FieldTestCase
{
    private const TYPES = ['text' => 'Text', 'email' => 'E-mail', 'number' => 'Number', 'select' => 'List', 'radio' => 'Buttons'];

    public function test_the_page_gets_the_types_the_grid_and_the_limit(): void
    {
        $field = $this->builder();
        $array = $field->toArray();

        $this->assertSame('formBuilder', $field->getType());
        $this->assertSame(self::TYPES, $array['types']);
        $this->assertSame(['select', 'radio'], $array['choiceTypes']);
        $this->assertSame(12, $array['columns']);
        $this->assertSame(100, $array['maxItems']);
    }

    public function test_bare_builder_has_no_types_and_lists_its_choice_types_in_order(): void
    {
        $array = FormBuilderField::make('builder')->choiceTypes(['a' => 'select', 'b' => 'radio'])->toArray();

        $this->assertSame([], $array['types']);
        $this->assertSame(['select', 'radio'], $array['choiceTypes']);
    }

    public function test_the_limit_is_at_least_one_field(): void
    {
        $this->assertSame(1, FormBuilderField::make('builder')->maxItems(0)->toArray()['maxItems']);
        $this->assertSame(1, FormBuilderField::make('builder')->maxItems(-3)->toArray()['maxItems']);
        $this->assertSame(7, FormBuilderField::make('builder')->maxItems(7)->toArray()['maxItems']);
    }

    public function test_the_list_is_optional_unless_the_field_is_required(): void
    {
        $rules = $this->builder()->getValidationRules();

        $this->assertSame(['nullable', 'array'], array_slice($rules, 0, 2));
        $this->assertInstanceOf(Closure::class, $rules[2]);
        $this->assertCount(3, $rules);
        $this->assertSame('required', $this->builder()->required()->getValidationRules()[0]);
        $this->assertSame(['nullable', 'min:2', 'array'], array_slice($this->builder()->rules(['min:2'])->getValidationRules(), 0, 3));

        $this->assertTrue($this->passes($this->builder(), null));
        $this->assertTrue($this->passes($this->builder(), []));
        $this->assertFalse($this->passes($this->builder()->required(), []));
        $this->assertFalse($this->passes($this->builder(), 'name,email'));
    }

    public function test_a_value_that_is_not_a_list_is_left_to_the_array_rule(): void
    {
        $messages = [];
        $this->builder()->getValidationRules()[2]('builder', 'name,email', static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertSame([], $messages);
    }

    /**
     * @return array<string, array{array<string,mixed>, bool}>
     */
    public static function items(): array
    {
        $valid = self::item();

        return [
            'the smallest field' => [$valid, true],
            'every setting' => [[...$valid, 'id' => 3, 'required' => true, 'min' => 2, 'max' => 40, 'hint' => ['en' => str_repeat('h', 500)], 'placeholder' => ['en' => str_repeat('p', 255)]], true],
            'an id as text' => [[...$valid, 'id' => '3'], true],
            'an id of letters' => [[...$valid, 'id' => 'three'], false],
            'an empty id' => [[...$valid, 'id' => null], true],
            'no code' => [self::without($valid, 'name'), false],
            'a code of a number' => [[...$valid, 'name' => 5], false],
            'a code with a dash and an underscore' => [[...$valid, 'name' => 'e-mail_2'], true],
            'a code with a space' => [[...$valid, 'name' => 'e mail'], false],
            'a Cyrillic code' => [[...$valid, 'name' => 'почта'], false],
            'a code of 100 characters' => [[...$valid, 'name' => str_repeat('a', 100)], true],
            'a code of 101 characters' => [[...$valid, 'name' => str_repeat('a', 101)], false],
            'a script for a code' => [[...$valid, 'name' => '<script>'], false],
            'no type' => [self::without($valid, 'type'), false],
            'an unknown type' => [[...$valid, 'type' => 'file'], false],
            'a type as a list' => [[...$valid, 'type' => ['text']], false],
            'not required' => [[...$valid, 'required' => false], true],
            'required left empty' => [[...$valid, 'required' => null], true],
            'required as a word' => [[...$valid, 'required' => 'yes'], false],
            'no width' => [self::without($valid, 'width'), false],
            'one column' => [[...$valid, 'width' => 1], true],
            'twelve columns' => [[...$valid, 'width' => 12], true],
            'no columns' => [[...$valid, 'width' => 0], false],
            'thirteen columns' => [[...$valid, 'width' => 13], false],
            'half a column' => [[...$valid, 'width' => 6.5], false],
            'a least as a word' => [[...$valid, 'min' => 'two'], false],
            'a most as a word' => [[...$valid, 'max' => 'forty'], false],
            'a most below the least' => [[...$valid, 'min' => 5, 'max' => 3], false],
            'a most equal to the least' => [[...$valid, 'min' => 5, 'max' => 5], true],
            'a most without a least' => [[...$valid, 'max' => 3], true],
            'a most with an empty least' => [[...$valid, 'min' => null, 'max' => 3], true],
            'a most left empty' => [[...$valid, 'min' => 2, 'max' => null], true],
            'a negative least for a number' => [[...$valid, 'min' => -10, 'max' => -1], true],
            'no label' => [self::without($valid, 'label'), false],
            'a label in another language only' => [[...$valid, 'label' => ['ru' => 'Почта']], false],
            'a blank label' => [[...$valid, 'label' => ['en' => '  ']], false],
            'a label as text' => [[...$valid, 'label' => 'E-mail'], false],
            'a label left empty in another language' => [[...$valid, 'label' => ['en' => 'E-mail', 'ru' => null]], true],
            'a label of 255 characters' => [[...$valid, 'label' => ['en' => str_repeat('l', 255)]], true],
            'a label of 256 characters' => [[...$valid, 'label' => ['en' => str_repeat('l', 256)]], false],
            'a label as a list' => [[...$valid, 'label' => ['en' => ['E-mail']]], false],
            'a hint as text' => [[...$valid, 'hint' => 'Write it'], false],
            'an empty hint' => [[...$valid, 'hint' => null], true],
            'a hint as a number' => [[...$valid, 'hint' => ['en' => 5]], false],
            'a hint of 501 characters' => [[...$valid, 'hint' => ['en' => str_repeat('h', 501)]], false],
            'a placeholder of 256 characters' => [[...$valid, 'placeholder' => ['en' => str_repeat('p', 256)]], false],
            'a list without options' => [[...$valid, 'type' => 'select'], false],
            'a list with options' => [[...$valid, 'type' => 'select', 'options' => ['en' => "One\nTwo"]], true],
            'buttons without options' => [[...$valid, 'type' => 'radio', 'options' => ['ru' => 'Один']], false],
            'options of 5000 characters' => [[...$valid, 'type' => 'select', 'options' => ['en' => str_repeat('o', 5000)]], true],
            'options of 5001 characters' => [[...$valid, 'type' => 'select', 'options' => ['en' => str_repeat('o', 5001)]], false],
            'a text field needs no options' => [[...$valid, 'type' => 'text', 'options' => null], true],
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     */
    #[DataProvider('items')]
    public function test_each_field_is_checked_against_its_rules(array $item, bool $passes): void
    {
        $this->assertSame($passes, $this->passes($this->builder(), [$item]));
    }

    public function test_the_first_problem_is_told_with_the_label_of_its_field(): void
    {
        $this->assertSame(
            ['Field "E-mail": The Width field must be between 1 and 12.'],
            $this->errors([self::item(['width' => 13])]),
        );
        $this->assertSame(
            ['Field "E-mail": The Width field must be an integer.'],
            $this->errors([self::item(['width' => 'wide', 'min' => 'x'])]),
        );
    }

    public function test_a_field_without_a_usable_label_is_told_by_its_place(): void
    {
        $this->assertSame(['Field "#2": The Label field is required.'], $this->errors([self::item(), self::item(['name' => 'b', 'label' => ['en' => '  ']])]));
        $this->assertSame(['Field "#1": The Label field must be a string.'], $this->errors([self::item(['label' => ['en' => ['x']]])]));
        $this->assertSame(['Field "#2": The Code field is required.'], $this->errors([7 => self::item(), 9 => self::item(['name' => null, 'label' => null])]));
        $this->assertSame(['Field "E-mail": The Code field is required.'], $this->errors([self::item(['name' => null, 'label' => ['en' => '  E-mail ']])]));
    }

    /**
     * @return array<string, array{array<string,mixed>, string}>
     */
    public static function attributes(): array
    {
        return [
            'code' => [['name' => null], 'Code'],
            'type' => [['type' => null], 'Type'],
            'required' => [['required' => 'yes'], 'Required'],
            'width' => [['width' => null], 'Width'],
            'least' => [['min' => 'x'], 'Minimum'],
            'most' => [['max' => 'x'], 'Maximum'],
            'label' => [['label' => 'x'], 'Label'],
            'label in a language' => [['label' => ['en' => 'E-mail', 'ru' => 5]], 'Label'],
            'hint' => [['hint' => 'x'], 'Hint under the field'],
            'hint in a language' => [['hint' => ['en' => 5]], 'Hint under the field'],
            'placeholder' => [['placeholder' => 'x'], 'Placeholder'],
            'placeholder in a language' => [['placeholder' => ['en' => 5]], 'Placeholder'],
            'options' => [['options' => 'x'], 'Options'],
            'options of a list' => [['type' => 'select'], 'Options'],
        ];
    }

    /**
     * @param  array<string,mixed>  $change
     */
    #[DataProvider('attributes')]
    public function test_a_problem_names_the_setting_as_the_editor_sees_it(array $change, string $setting): void
    {
        $errors = $this->errors([self::item($change)]);

        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('Field "', $errors[0]);
        $this->assertStringContainsString(" {$setting} ", $errors[0]);
    }

    public function test_two_fields_may_not_share_a_code(): void
    {
        $this->assertSame(
            ['Field "Second": Two fields have the code "email".'],
            $this->errors([self::item(), self::item(['label' => ['en' => 'Second']]), self::item(['name' => 'x', 'width' => 0])]),
        );
        $this->assertSame([], $this->errors([self::item(['name' => 'email']), self::item(['name' => 'Email'])]));
        $this->assertSame([], $this->errors([self::item(['name' => '1']), self::item(['name' => '01'])]));
        $this->assertSame([], $this->errors([self::item(['name' => 'a']), self::item(['name' => 'b']), self::item(['name' => 'c'])]));
    }

    public function test_a_broken_entry_stops_the_check(): void
    {
        $this->assertSame(['The list of fields is broken. Reload the page and try again.'], $this->errors([self::item(), 'email', ['width' => 0]]));
    }

    public function test_the_list_is_limited(): void
    {
        $field = $this->builder()->maxItems(2);

        $this->assertSame([], $this->errors([self::item(['name' => 'a']), self::item(['name' => 'b'])], $field));
        $this->assertSame(['No more than 2 fields.'], $this->errors([self::item(['name' => 'a']), self::item(['name' => 'b']), ['width' => 0]], $field));

        $fields = array_map(static fn (int $i): array => self::item(['name' => "f{$i}"]), range(1, 100));
        $this->assertSame([], $this->errors($fields));
        $this->assertSame(['No more than 100 fields.'], $this->errors([...$fields, self::item(['name' => 'f101'])]));
    }

    public function test_the_problems_speak_every_language(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            foreach (['item_error', 'name_taken', 'invalid', 'too_many', 'name', 'type', 'required', 'width', 'min', 'max', 'label', 'hint', 'placeholder', 'options'] as $key) {
                $this->assertStringStartsNotWith('atrium::', __("atrium::fields.form_builder.{$key}"), "{$locale}: {$key}");
            }
        }
    }

    private function builder(): FormBuilderField
    {
        return FormBuilderField::make('builder')->types(self::TYPES)->choiceTypes(['select', 'radio']);
    }

    /**
     * @param  array<int,mixed>  $value
     * @return array<int,string>
     */
    private function errors(array $value, ?FormBuilderField $field = null): array
    {
        $field ??= $this->builder();

        return Validator::make(['builder' => $value], ['builder' => $field->getValidationRules()])->errors()->get('builder');
    }

    /**
     * @param  array<string,mixed>  $change
     * @return array<string,mixed>
     */
    private static function item(array $change = []): array
    {
        return [...['name' => 'email', 'type' => 'email', 'width' => 6, 'label' => ['en' => 'E-mail']], ...$change];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private static function without(array $item, string $key): array
    {
        unset($item[$key]);

        return $item;
    }
}

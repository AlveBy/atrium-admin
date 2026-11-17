<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Tests\Fixtures\Post;
use PHPUnit\Framework\Attributes\DataProvider;

final class FieldTest extends FieldTestCase
{
    private function plainField(string $name = 'note'): Field
    {
        $class = new class extends Field
        {
            public function getType(): string
            {
                return 'plain';
            }
        };

        return $class::make($name);
    }

    public function test_a_new_field_is_optional_editable_and_on_the_params_tab(): void
    {
        $field = $this->plainField();

        $this->assertSame([
            'name' => 'note',
            'type' => 'plain',
            'component' => 'plainField',
            'label' => '',
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'hint' => null,
            'default' => null,
            'placeholder' => null,
            'attributes' => [],
            'tab' => 'params',
            'tabOrder' => 0,
            'ai' => null,
        ], $field->toArray());
        $this->assertSame('note', $field->getName());
        $this->assertSame('note', $field->getColumn());
        $this->assertSame('', $field->getLabel());
        $this->assertFalse($field->isRequired());
        $this->assertNull($field->getDefault());
        $this->assertSame('params', $field->getTab());
        $this->assertSame(0, $field->getTabOrder());
        $this->assertTrue($field->shouldShowOnCreate());
        $this->assertTrue($field->shouldShowOnEdit());
    }

    public function test_every_setting_reaches_the_form(): void
    {
        $field = $this->plainField()
            ->label('Заметка')
            ->required()
            ->disabled()
            ->readonly()
            ->hint('Не больше абзаца')
            ->default('—')
            ->placeholder('Напишите…')
            ->withAttributes(['data-x' => '1'])
            ->withAttributes(['data-y' => '2', 'data-x' => '3'])
            ->tab('seo')
            ->tabOrder(7);

        $this->assertSame([
            'name' => 'note',
            'type' => 'plain',
            'component' => 'plainField',
            'label' => 'Заметка',
            'required' => true,
            'disabled' => true,
            'readonly' => true,
            'hint' => 'Не больше абзаца',
            'default' => '—',
            'placeholder' => 'Напишите…',
            'attributes' => ['data-x' => '3', 'data-y' => '2'],
            'tab' => 'seo',
            'tabOrder' => 7,
            'ai' => null,
        ], $field->toArray());
        $this->assertSame('Заметка', $field->getLabel());
        $this->assertTrue($field->isRequired());
        $this->assertSame('—', $field->getDefault());
        $this->assertSame('seo', $field->getTab());
        $this->assertSame(7, $field->getTabOrder());
    }

    public function test_the_flags_can_be_switched_off_again(): void
    {
        $array = $this->plainField()->required()->required(false)->disabled()->disabled(false)->readonly()->readonly(false)->toArray();

        $this->assertFalse($array['required']);
        $this->assertFalse($array['disabled']);
        $this->assertFalse($array['readonly']);
    }

    public function test_the_column_can_differ_from_the_name(): void
    {
        $field = $this->plainField('title')->column('name');

        $this->assertSame('title', $field->getName());
        $this->assertSame('name', $field->getColumn());
    }

    /**
     * @return array<string, array{\Closure(Field):Field, bool, bool}>
     */
    public static function visibility(): array
    {
        return [
            'hidden on create' => [fn (Field $f): Field => $f->hideOnCreate(), false, true],
            'hidden on edit' => [fn (Field $f): Field => $f->hideOnEdit(), true, false],
            'not shown on create' => [fn (Field $f): Field => $f->showOnCreate(false), false, true],
            'not shown on edit' => [fn (Field $f): Field => $f->showOnEdit(false), true, false],
            'shown again on create' => [fn (Field $f): Field => $f->hideOnCreate()->showOnCreate(), true, true],
            'shown again on edit' => [fn (Field $f): Field => $f->hideOnEdit()->showOnEdit(), true, true],
            'only on create' => [fn (Field $f): Field => $f->hideOnCreate()->onlyOnCreate(), true, false],
            'only on edit' => [fn (Field $f): Field => $f->hideOnEdit()->onlyOnEdit(), false, true],
        ];
    }

    #[DataProvider('visibility')]
    public function test_a_field_can_be_left_off_the_create_or_the_edit_form(\Closure $configure, bool $onCreate, bool $onEdit): void
    {
        $field = $configure($this->plainField());

        $this->assertSame($onCreate, $field->shouldShowOnCreate());
        $this->assertSame($onEdit, $field->shouldShowOnEdit());
    }

    /**
     * @return array<string, array{\Closure(Field):Field, array<int, mixed>}>
     */
    public static function rules(): array
    {
        return [
            'optional' => [fn (Field $f): Field => $f, ['nullable']],
            'required' => [fn (Field $f): Field => $f->required(), ['required']],
            'one rule as a string' => [fn (Field $f): Field => $f->rules('alpha'), ['nullable', 'alpha']],
            'rules as an array' => [fn (Field $f): Field => $f->rules(['alpha', 'min:2']), ['nullable', 'alpha', 'min:2']],
            'rules replace the earlier ones' => [fn (Field $f): Field => $f->rules('alpha')->rules('digits:4'), ['nullable', 'digits:4']],
            'required with rules' => [fn (Field $f): Field => $f->required()->rules('alpha'), ['required', 'alpha']],
            'required already among the rules' => [fn (Field $f): Field => $f->required()->rules(['alpha', 'required']), ['alpha', 'required']],
            'nullable already among the rules' => [fn (Field $f): Field => $f->rules(['nullable', 'alpha']), ['nullable', 'alpha']],
            'unique in a table, by the name' => [fn (Field $f): Field => $f->unique('posts'), ['nullable', 'unique:posts,note']],
            'unique in a table, by a column' => [fn (Field $f): Field => $f->unique('posts', 'name'), ['nullable', 'unique:posts,name']],
            'minimum length' => [fn (Field $f): Field => $f->minLength(3), ['nullable', 'min:3']],
            'rules add up with unique and min' => [fn (Field $f): Field => $f->rules('alpha')->minLength(2)->unique('posts'), ['nullable', 'alpha', 'min:2', 'unique:posts,note']],
        ];
    }

    /**
     * @param  array<int, mixed>  $expected
     */
    #[DataProvider('rules')]
    public function test_the_rules_start_with_required_or_nullable(\Closure $configure, array $expected): void
    {
        $field = $configure($this->plainField());

        $this->assertSame($expected, $field->getValidationRules());
        $this->assertSame($expected, $field->getValidationRules(FieldContext::create('x', true)));
    }

    /**
     * @return array<string, array{bool, mixed, bool}>
     */
    public static function presence(): array
    {
        return [
            'optional, missing' => [false, self::MISSING, true],
            'optional, null' => [false, null, true],
            'optional, empty' => [false, '', true],
            'optional, given' => [false, 'x', true],
            'required, missing' => [true, self::MISSING, false],
            'required, null' => [true, null, false],
            'required, empty' => [true, '', false],
            'required, only spaces' => [true, '   ', false],
            'required, empty array' => [true, [], false],
            'required, given' => [true, 'x', true],
            'required, zero' => [true, '0', true],
        ];
    }

    #[DataProvider('presence')]
    public function test_a_required_field_refuses_an_empty_value(bool $required, mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes($this->plainField()->required($required), $value));
    }

    public function test_a_unique_field_refuses_a_value_already_taken(): void
    {
        Post::query()->create(['name' => 'Taken']);

        $field = $this->plainField('name')->unique('posts');

        $this->assertFalse($this->passes($field, 'Taken'));
        $this->assertTrue($this->passes($field, 'Free'));
    }

    public function test_a_plain_field_saves_and_shows_the_value_unchanged(): void
    {
        $field = $this->plainField();

        foreach (['text', '', null, 0, ['a' => 1], '<script>x</script>'] as $value) {
            $this->assertSame($value, $field->transformValue($value));
            $this->assertSame($value, $field->displayValue($value));
        }
    }

    public function test_json_is_the_same_as_the_array(): void
    {
        $field = TextField::make('name')->label('Name')->required();

        $this->assertSame($field->toArray(), $field->jsonSerialize());
        $this->assertSame(json_encode($field->toArray()), json_encode($field));
    }

    public function test_only_fields_that_can_be_generated_carry_the_ai_option(): void
    {
        $this->assertNull(TextField::make('a')->toArray()['ai'], 'can be generated, but was not asked to');
        $this->assertNull(TextField::make('a')->getAi());
        $this->assertNull(NumberField::make('a')->toArray()['ai'], 'cannot be generated');
        $this->assertFalse(method_exists(NumberField::make('a'), 'getAi'));
    }

    /**
     * @return array<string, array{\Closure(): Field, array{task:string,from:array<int,string>}}>
     */
    public static function aiOptions(): array
    {
        return [
            'defaults' => [fn (): Field => TextField::make('a')->ai(), ['task' => 'describe', 'from' => []]],
            'from one field' => [fn (): Field => TextareaField::make('preview_text')->ai('preview', from: 'detail_text'), ['task' => 'preview', 'from' => ['detail_text']]],
            'from several fields' => [fn (): Field => ContentField::make('detail_text')->ai('describe', ['name', 'preview_text']), ['task' => 'describe', 'from' => ['name', 'preview_text']]],
            'keys of the list are dropped' => [fn (): Field => TextField::make('alt')->ai('alt', ['x' => 'picture', 'y' => 'name']), ['task' => 'alt', 'from' => ['picture', 'name']]],
            'from nothing' => [fn (): Field => TextField::make('meta_title')->ai('seo_title', null), ['task' => 'seo_title', 'from' => []]],
        ];
    }

    /**
     * @param  array{task:string,from:array<int,string>}  $expected
     */
    #[DataProvider('aiOptions')]
    public function test_the_ai_option_says_what_to_generate_and_from_which_fields(\Closure $make, array $expected): void
    {
        $field = $make();

        $this->assertSame($expected, $field->getAi());
        $this->assertSame($expected, $field->toArray()['ai']);
    }

    public function test_the_context_tells_create_from_edit(): void
    {
        $post = new Post(['name' => 'A']);

        $default = new FieldContext;
        $create = FieldContext::create('new', true);
        $edit = FieldContext::edit($post, 'changed', true);
        $bare = FieldContext::edit(null);

        $this->assertTrue($default->isCreate());
        $this->assertFalse($default->isEdit());
        $this->assertNull($default->record);
        $this->assertNull($default->value);
        $this->assertFalse($default->present);

        $this->assertTrue($create->isCreate());
        $this->assertFalse($create->isEdit());
        $this->assertSame('new', $create->value);
        $this->assertTrue($create->present);
        $this->assertSame(FieldContext::CREATE, $create->context);

        $this->assertTrue($edit->isEdit());
        $this->assertFalse($edit->isCreate());
        $this->assertSame($post, $edit->record);
        $this->assertSame('changed', $edit->value);
        $this->assertTrue($edit->present);
        $this->assertSame(FieldContext::EDIT, $edit->context);

        $this->assertTrue($bare->isEdit());
        $this->assertNull($bare->value);
        $this->assertFalse($bare->present);
        $this->assertFalse(FieldContext::create()->present);
    }
}

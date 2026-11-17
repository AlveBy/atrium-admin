<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\WysiwygField;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The text inputs: one line, several lines, the Text/HTML/Visual switch and
 * the visual editor.
 */
final class TextFieldsTest extends FieldTestCase
{
    public function test_a_text_field_is_a_plain_input_hinting_255_characters(): void
    {
        $field = TextField::make('name');

        $this->assertSame('text', $field->getType());
        $this->assertSame('textField', $field->getComponent());
        $this->assertSame(['maxLength' => 255, 'inputType' => 'text', 'prefix' => null, 'suffix' => null], array_intersect_key($field->toArray(), array_flip(['maxLength', 'inputType', 'prefix', 'suffix'])));
        $this->assertSame(['nullable', 'string', 'max:65535'], $field->getValidationRules());
    }

    public function test_a_text_field_shows_a_prefix_and_a_suffix(): void
    {
        $array = TextField::make('domain')->prefix('https://')->suffix('.by')->toArray();

        $this->assertSame('https://', $array['prefix']);
        $this->assertSame('.by', $array['suffix']);
    }

    /**
     * @return array<string, array{\Closure(TextField):TextField, string, array<int, string>}>
     */
    public static function inputTypes(): array
    {
        return [
            'email checks the address' => [fn (TextField $f): TextField => $f->email(), 'email', ['nullable', 'email', 'string', 'max:65535']],
            'url checks the address' => [fn (TextField $f): TextField => $f->url(), 'url', ['nullable', 'url', 'string', 'max:65535']],
            'tel adds no rule' => [fn (TextField $f): TextField => $f->tel(), 'tel', ['nullable', 'string', 'max:65535']],
            'password adds no rule' => [fn (TextField $f): TextField => $f->password(), 'password', ['nullable', 'string', 'max:65535']],
            'any other type as given' => [fn (TextField $f): TextField => $f->inputType('search'), 'search', ['nullable', 'string', 'max:65535']],
        ];
    }

    /**
     * @param  array<int, string>  $rules
     */
    #[DataProvider('inputTypes')]
    public function test_the_input_type_sets_the_keyboard_and_the_rules(\Closure $configure, string $type, array $rules): void
    {
        $field = $configure(TextField::make('contact'));

        $this->assertSame($type, $field->toArray()['inputType']);
        $this->assertSame($rules, $field->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function emails(): array
    {
        return [
            'plain' => ['shop@example.com', true],
            'empty, the field is optional' => ['', true],
            'no at sign' => ['shop.example.com', false],
            'two at signs' => ['a@@example.com', false],
            'not a string' => [['shop@example.com'], false],
        ];
    }

    #[DataProvider('emails')]
    public function test_an_email_field_refuses_what_is_not_an_address(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(TextField::make('email')->email(), $value));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function urls(): array
    {
        return [
            'https' => ['https://example.com/page', true],
            'Cyrillic domain' => ['https://пример.бел/', true],
            'no scheme' => ['example.com', false],
            'a script' => ['javascript:alert(1)', false],
        ];
    }

    #[DataProvider('urls')]
    public function test_a_url_field_refuses_what_is_not_an_address(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(TextField::make('site')->url(), $value));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function names(): array
    {
        return [
            'plain' => ['Garden chair', true],
            'one character' => ['A', true],
            'longest allowed' => [str_repeat('a', 20), true],
            'one too long' => [str_repeat('a', 21), false],
            'longest allowed in Cyrillic' => [str_repeat('я', 20), true],
            'one too long in Cyrillic' => [str_repeat('я', 21), false],
            'Chinese' => ['花园椅', true],
            'Japanese' => ['ガーデンチェア', true],
            'Arabic, right to left' => ['كرسي حديقة', true],
            'Hebrew, right to left' => ['כיסא גן', true],
            'emoji' => ['Chair 🪑', true],
            'combining characters' => ["e\u{0301}te\u{0301}", true],
            'HTML is kept as text' => ['<b>Chair</b>', true],
            'empty' => ['', true],
            'null' => [null, true],
            'not a string' => [['array'], false],
            'a number is not a string' => [42, false],
        ];
    }

    #[DataProvider('names')]
    public function test_a_text_field_with_a_max_length_refuses_longer_text(mixed $value, bool $passes): void
    {
        $field = TextField::make('name')->maxLength(20);

        $this->assertSame(20, $field->toArray()['maxLength']);
        $this->assertSame(['nullable', 'string', 'max:20'], $field->getValidationRules());
        $this->assertSame($passes, $this->passes($field, $value));
    }

    public function test_without_a_max_length_a_text_field_takes_what_a_text_column_holds(): void
    {
        $field = TextField::make('meta_keywords');

        $this->assertTrue($this->passes($field, str_repeat('a', 279)), 'longer than the 255 of the input hint');
        $this->assertTrue($this->passes($field, str_repeat('a', 65535)));
        $this->assertFalse($this->passes($field, str_repeat('a', 65536)));
    }

    public function test_a_textarea_has_four_rows_and_no_limit(): void
    {
        $field = TextareaField::make('preview_text');

        $this->assertSame('textarea', $field->getType());
        $this->assertSame(4, $field->toArray()['rows']);
        $this->assertNull($field->toArray()['maxLength']);
        $this->assertFalse($field->toArray()['autoResize']);
        $this->assertSame(['nullable', 'string'], $field->getValidationRules());
        $this->assertTrue($this->passes($field, str_repeat('слово ', 20000)));
    }

    public function test_a_textarea_takes_its_rows_size_and_limit(): void
    {
        $field = TextareaField::make('preview_text')->rows(10)->autoResize()->maxLength(5);

        $this->assertSame(10, $field->toArray()['rows']);
        $this->assertTrue($field->toArray()['autoResize']);
        $this->assertSame(5, $field->toArray()['maxLength']);
        $this->assertSame(['nullable', 'string', 'max:5'], $field->getValidationRules());
        $this->assertTrue($this->passes($field, 'Пятёр'));
        $this->assertFalse($this->passes($field, 'Шестью'));
        $this->assertFalse($this->passes($field, ['five']));
        $this->assertFalse(TextareaField::make('a')->autoResize()->autoResize(false)->toArray()['autoResize']);
    }

    public function test_a_content_field_offers_all_three_modes_starting_visual(): void
    {
        $field = ContentField::make('detail_text');
        $array = $field->toArray();

        $this->assertSame('content', $field->getType());
        $this->assertSame('wysiwyg', $array['defaultMode']);
        $this->assertSame(['text', 'html', 'wysiwyg'], $array['modes']);
        $this->assertSame(6, $array['rows']);
        $this->assertSame(200, $array['minHeight']);
        $this->assertSame(['bold', 'italic', 'underline', 'strike', 'color', 'link', 'bulletList', 'orderedList', 'align', 'heading', 'fontSize', 'image', 'file', 'table'], $array['toolbar']);
        $this->assertSame(['nullable'], $field->getValidationRules(), 'HTML of any length is taken');
    }

    public function test_a_content_field_takes_its_modes_rows_toolbar_and_height(): void
    {
        $array = ContentField::make('embed')
            ->modes(['text', 'html'])
            ->defaultMode('html')
            ->rows(12)
            ->toolbar(['bold'])
            ->minHeight(400)
            ->toArray();

        $this->assertSame(['text', 'html'], $array['modes']);
        $this->assertSame('html', $array['defaultMode']);
        $this->assertSame(12, $array['rows']);
        $this->assertSame(['bold'], $array['toolbar']);
        $this->assertSame(400, $array['minHeight']);
    }

    public function test_a_simple_content_field_has_no_visual_editor_and_a_full_one_has_it_again(): void
    {
        $simple = ContentField::make('note')->simple()->toArray();
        $full = ContentField::make('note')->simple()->full()->toArray();

        $this->assertSame(['text', 'html'], $simple['modes']);
        $this->assertSame('text', $simple['defaultMode']);
        $this->assertSame(['text', 'html', 'wysiwyg'], $full['modes']);
        $this->assertSame('wysiwyg', $full['defaultMode']);
    }

    public function test_a_visual_editor_has_the_basic_toolbar_with_images(): void
    {
        $field = WysiwygField::make('body');
        $array = $field->toArray();

        $this->assertSame('wysiwyg', $field->getType());
        $this->assertSame(['bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'heading', 'image', 'file'], $array['toolbar']);
        $this->assertSame(200, $array['minHeight']);
        $this->assertNull($array['maxHeight']);
        $this->assertTrue($array['allowImages']);
        $this->assertNull($array['maxLength']);
        $this->assertSame(['nullable'], $field->getValidationRules());
    }

    public function test_a_visual_editor_takes_its_toolbar_heights_and_limit(): void
    {
        $field = WysiwygField::make('body')->toolbar(['bold', 'link'])->minHeight(100)->maxHeight(600)->maxLength(13);
        $array = $field->toArray();

        $this->assertSame(['bold', 'link'], $array['toolbar']);
        $this->assertSame(100, $array['minHeight']);
        $this->assertSame(600, $array['maxHeight']);
        $this->assertSame(13, $array['maxLength']);
        $this->assertSame(['nullable', 'max:13'], $field->getValidationRules());
        $this->assertTrue($this->passes($field, '<p>Привет</p>'), 'the limit counts characters with the tags');
        $this->assertFalse($this->passes($field, '<p>Привет!</p>'));
    }

    public function test_a_simple_visual_editor_has_basic_formatting_and_no_images(): void
    {
        $array = WysiwygField::make('body')->simple()->toArray();

        $this->assertSame(['bold', 'italic', 'link', 'bulletList', 'orderedList'], $array['toolbar']);
        $this->assertFalse($array['allowImages']);
    }

    public function test_a_full_visual_editor_has_every_button(): void
    {
        $this->assertSame([
            'bold', 'italic', 'underline', 'strike',
            'link', 'bulletList', 'orderedList',
            'heading', 'blockquote', 'code',
            'image', 'file', 'horizontalRule',
            'undo', 'redo',
        ], WysiwygField::make('body')->simple()->full()->toArray()['toolbar']);
    }

    public function test_switching_images_off_takes_the_image_button_away(): void
    {
        $array = WysiwygField::make('body')->allowImages(false)->toArray();

        $this->assertFalse($array['allowImages']);
        $this->assertNotContains('image', $array['toolbar']);
        $this->assertSame(['bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'heading', 'file'], array_values($array['toolbar']));
    }

    public function test_switching_images_on_keeps_the_toolbar(): void
    {
        $array = WysiwygField::make('body')->toolbar(['bold', 'image'])->allowImages()->toArray();

        $this->assertTrue($array['allowImages']);
        $this->assertSame(['bold', 'image'], $array['toolbar']);
    }
}

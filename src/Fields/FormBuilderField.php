<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Support\ContentLanguages;
use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * The fields of a form that visitors fill in, built on the record's own
 * page: added, dragged into order, set up, laid out on a grid of twelve
 * columns, with a preview beside them. The forms module builds on it.
 *
 *     FormBuilderField::make('builder')
 *         ->types(['text' => 'Text', 'select' => 'List', …])
 *         ->choiceTypes(['select', 'radio'])
 *
 * The value is a list of fields, in order:
 *
 *     ['id' => 3, 'name' => 'email', 'type' => 'email', 'required' => true,
 *      'width' => 6, 'min' => null, 'max' => null,
 *      'label' => ['en' => 'E-mail'], 'hint' => ['en' => '…'],
 *      'placeholder' => ['en' => '…'], 'options' => ['en' => "One\nTwo"]]
 *
 * The texts hold one value per content language. The builder has no column
 * of its own: the model writes the list in afterAdminSave() and gives it
 * back as an attribute.
 */
class FormBuilderField extends Field
{
    /** The grid a field's width is counted on. */
    public const COLUMNS = 12;

    /** Texts kept per language. */
    public const TEXTS = ['label' => 255, 'hint' => 500, 'placeholder' => 255, 'options' => 5000];

    /** @var array<string,string> type => label */
    protected array $types = [];

    /** @var array<int,string> */
    protected array $choiceTypes = [];

    protected int $maxItems = 100;

    public function getType(): string
    {
        return 'formBuilder';
    }

    /**
     * The types a field may have, with their names.
     *
     * @param  array<string,string>  $types
     */
    public function types(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    /**
     * Types whose value is one of the options: they need at least one.
     *
     * @param  array<int,string>  $types
     */
    public function choiceTypes(array $types): static
    {
        $this->choiceTypes = array_values($types);

        return $this;
    }

    public function maxItems(int $items): static
    {
        $this->maxItems = max(1, $items);

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'types' => $this->types,
            'choiceTypes' => $this->choiceTypes,
            'columns' => self::COLUMNS,
            'maxItems' => $this->maxItems,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        return [...$this->baseValidationRules($context), 'array', $this->itemsRule()];
    }

    /**
     * Every field against its rules; the first problem is told with the
     * field's label, so the editor knows which one to open.
     */
    private function itemsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            if (count($value) > $this->maxItems) {
                $fail(__('atrium::fields.form_builder.too_many', ['max' => $this->maxItems]));

                return;
            }

            $default = app(ContentLanguages::class)->default();
            $names = [];

            foreach (array_values($value) as $index => $item) {
                if (! is_array($item)) {
                    $fail(__('atrium::fields.form_builder.invalid'));

                    return;
                }

                $validator = Validator::make($item, $this->itemRules($item, $default), [], $this->itemAttributes());
                $name = $item['name'] ?? null;
                $message = $validator->fails()
                    ? $validator->errors()->first()
                    : (in_array($name, $names, true) ? __('atrium::fields.form_builder.name_taken', ['name' => $name]) : null);

                if ($message === null) {
                    $names[] = $name;

                    continue;
                }

                $label = data_get($item, "label.{$default}");

                $fail(__('atrium::fields.form_builder.item_error', [
                    'item' => is_string($label) && trim($label) !== '' ? trim($label) : '#'.($index + 1),
                    'message' => $message,
                ]));

                return;
            }
        };
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,array<int,mixed>>
     */
    private function itemRules(array $item, string $default): array
    {
        $rules = [
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'alpha_dash:ascii', 'max:100'],
            'type' => ['required', 'in:'.implode(',', array_keys($this->types))],
            'required' => ['nullable', 'boolean'],
            'width' => ['required', 'integer', 'between:1,'.self::COLUMNS],
            'min' => ['nullable', 'integer'],
            'max' => ['nullable', 'integer', ...(is_numeric($item['min'] ?? null) ? ['gte:min'] : [])],
        ];

        // The texts may be left empty in other languages, not in the default
        // one. These go before the wildcards: a key named after them would
        // replace what the wildcard gives it instead of adding to it.
        $rules["label.{$default}"] = ['required'];

        if (in_array($item['type'] ?? null, $this->choiceTypes)) {
            $rules["options.{$default}"] = ['required'];
        }

        foreach (self::TEXTS as $text => $length) {
            $rules[$text] = ['nullable', 'array'];
            $rules["{$text}.*"] = ['nullable', 'string', "max:{$length}"];
        }

        return $rules;
    }

    /**
     * @return array<string,string>
     */
    private function itemAttributes(): array
    {
        $attributes = [];

        foreach (['name', 'type', 'required', 'width', 'min', 'max', 'label', 'hint', 'placeholder', 'options'] as $name) {
            $attributes[$name] = __("atrium::fields.form_builder.{$name}");
            $attributes["{$name}.*"] = $attributes[$name];
        }

        return $attributes;
    }
}

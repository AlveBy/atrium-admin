<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Items in a tree, each with fields of its own, ordered and nested by drag
 * and drop: the items of a menu, the steps of a route.
 *
 *     TreeField::make('tree')
 *         ->fields([
 *             TextField::make('title')->label('Title')->required(),
 *             LinkField::make('link')->label('Link'),
 *         ])
 *         ->titleField('title')
 *         ->maxDepth(3)
 *
 * The value is a list of items, each an array of its fields' values plus
 * `children` (the same list) and the `id` it came with. The tree has no
 * column of its own: the model writes it where it belongs, usually in
 * afterAdminSave(), and gives it back as an attribute for the form.
 */
class TreeField extends Field
{
    /** @var array<int,Field> */
    protected array $fields = [];

    protected string $titleField = 'title';

    /** 0 = any depth. */
    protected int $maxDepth = 0;

    protected int $maxItems = 500;

    protected ?string $addLabel = null;

    public function getType(): string
    {
        return 'tree';
    }

    /**
     * The fields of one item.
     *
     * @param  array<int,Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * @return array<int,Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * The field whose value names an item in the tree.
     */
    public function titleField(string $name): static
    {
        $this->titleField = $name;

        return $this;
    }

    /**
     * How many levels the tree may have; 1 keeps it a flat list.
     */
    public function maxDepth(int $depth): static
    {
        $this->maxDepth = max(0, $depth);

        return $this;
    }

    public function maxItems(int $items): static
    {
        $this->maxItems = max(1, $items);

        return $this;
    }

    public function addLabel(string $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $this->fields),
            'titleField' => $this->titleField,
            'maxDepth' => $this->maxDepth,
            'maxItems' => $this->maxItems,
            'addLabel' => $this->addLabel ?? __('atrium::fields.tree.add'),
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = $this->baseValidationRules($context);
        $rules[] = 'array';
        $rules[] = $this->itemsRule();

        return $rules;
    }

    /**
     * Every item, at any depth, against its fields' rules. Laravel's
     * `tree.*.title` reaches one level only.
     */
    private function itemsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $rules = [];
            $names = [];

            foreach ($this->fields as $field) {
                $rules[$field->getName()] = array_filter(
                    $field->getValidationRules(),
                    static fn (mixed $rule): bool => ! is_string($rule) || ! str_starts_with($rule, 'unique:'),
                );
                $names[$field->getName()] = $field->getLabel();
            }

            $count = 0;
            $walk = function (array $items, int $depth) use (&$walk, &$count, $rules, $names, $fail): bool {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        $fail(__('atrium::fields.tree.invalid'));

                        return false;
                    }

                    if (++$count > $this->maxItems) {
                        $fail(__('atrium::fields.tree.too_many', ['max' => $this->maxItems]));

                        return false;
                    }

                    if ($this->maxDepth > 0 && $depth > $this->maxDepth) {
                        $fail(__('atrium::fields.tree.too_deep', ['max' => $this->maxDepth]));

                        return false;
                    }

                    $validator = Validator::make($item, $rules, [], $names);

                    if ($validator->fails()) {
                        $title = trim((string) (is_scalar($item[$this->titleField] ?? null) ? $item[$this->titleField] : ''));
                        $fail(__('atrium::fields.tree.item_error', [
                            'item' => $title !== '' ? $title : '#'.$count,
                            'message' => $validator->errors()->first(),
                        ]));

                        return false;
                    }

                    $children = $item['children'] ?? [];

                    if (! is_array($children)) {
                        $fail(__('atrium::fields.tree.invalid'));

                        return false;
                    }

                    if ($children !== [] && ! $walk($children, $depth + 1)) {
                        return false;
                    }
                }

                return true;
            };

            $walk($value, 1);
        };
    }

    public function transformValue(mixed $value): mixed
    {
        return is_array($value) ? array_values($value) : [];
    }
}

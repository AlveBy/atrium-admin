<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class TreeSelectField extends Field
{
    protected ?string $modelClass = null;

    protected string $displayColumn = 'name';

    protected string $valueColumn = 'id';

    protected string $parentColumn = 'parent_id';

    protected bool $searchable = true;

    protected bool $clearable = true;

    protected bool $multiple = false;

    protected bool $expandAll = false;

    protected ?string $rootLabel = null;

    protected bool $rootLabelSet = false;

    public function getType(): string
    {
        return 'treeSelect';
    }

    /**
     * Set related model class
     */
    public function model(string $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Set display column
     */
    public function displayColumn(string $column): static
    {
        $this->displayColumn = $column;

        return $this;
    }

    /**
     * Set value column
     */
    public function valueColumn(string $column): static
    {
        $this->valueColumn = $column;

        return $this;
    }

    /**
     * Set parent column
     */
    public function parentColumn(string $column): static
    {
        $this->parentColumn = $column;

        return $this;
    }

    /**
     * Enable/disable search
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Enable/disable clear button
     */
    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    /**
     * Enable multiple selection
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Label of the "nothing selected" row at the very top of the tree.
     *
     * Without it the parent list shows only existing sections, and "create a
     * top-level section" becomes guesswork: an editor adding a new top-level
     * section saw only existing ones under "Parent section" and concluded a new
     * one could only be created inside an old one. The empty value worked
     * before too — there was just no way to pick it.
     *
     * null — no such row (as for linking an element to a section: "no section"
     * there is not a normal choice but an orphaned page).
     */
    public function rootLabel(?string $label): static
    {
        $this->rootLabel = $label;
        $this->rootLabelSet = true;

        return $this;
    }

    /**
     * A self-nested tree (`parent_id` → the same model) is exactly the case
     * where "nothing selected" means "top level".
     */
    protected function defaultRootLabel(): ?string
    {
        if ($this->multiple || $this->name !== $this->parentColumn) {
            return null;
        }

        return __('atrium::fields.tree.top_level');
    }

    /**
     * Expand all nodes by default
     */
    public function expandAll(bool $expand = true): static
    {
        $this->expandAll = $expand;

        return $this;
    }

    /**
     * Get tree options from model
     */
    public function getTreeOptions(): array
    {
        if (! $this->modelClass) {
            return [];
        }

        $items = $this->modelClass::orderBy('sort')
            ->orderBy($this->displayColumn)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->{$this->valueColumn},
                'label' => $item->{$this->displayColumn},
                'parent_id' => $item->{$this->parentColumn},
            ])
            ->toArray();

        return $this->buildTree($items);
    }

    /**
     * Build tree structure from flat array
     */
    protected function buildTree(array $items, $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, $item['id']);
                $node = [
                    'value' => $item['id'],
                    'label' => $item['label'],
                ];

                if (! empty($children)) {
                    $node['children'] = $children;
                }

                $tree[] = $node;
            }
        }

        return $tree;
    }

    protected function getFieldProps(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'displayColumn' => $this->displayColumn,
            'valueColumn' => $this->valueColumn,
            'parentColumn' => $this->parentColumn,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'multiple' => $this->multiple,
            'expandAll' => $this->expandAll,
            'rootLabel' => $this->rootLabelSet ? $this->rootLabel : $this->defaultRootLabel(),
            'options' => $this->getTreeOptions(),
            'model' => $this->modelClass,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        if ($this->multiple) {
            // `array` instead of `exists`, but without clobbering an explicit
            // ->rules() the way the previous `$rules = ['array']` reset did.
            $rules[] = 'array';

            return $rules;
        }

        if ($this->modelClass) {
            $table = (new $this->modelClass)->getTable();
            $rules[] = "exists:{$table},{$this->valueColumn}";
        }

        if ($rule = $this->notItsOwnAncestorRule($context)) {
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * A section cannot sit inside itself or inside its own descendant.
     *
     * The parent tree shows all sections, including the one being edited, and
     * `exists` lets such a choice through. HasAdminSlug then walks from the
     * section to the root for the slug path — and in a cycle that walk never
     * ends: the save does not fail with an error, it hangs the request.
     */
    protected function notItsOwnAncestorRule(?FieldContext $context): ?\Closure
    {
        if (! $this->modelClass || $this->multiple || $this->name !== $this->parentColumn) {
            return null;
        }

        $record = $context?->record;

        if (! $record || ! $record->exists) {
            return null;
        }

        $modelClass = $this->modelClass;
        $parentColumn = $this->parentColumn;

        return function (string $attribute, $value, \Closure $fail) use ($record, $modelClass, $parentColumn): void {
            if (! $value || ! is_scalar($value)) {
                return;
            }

            $parents = $modelClass::query()->pluck($parentColumn, 'id')->all();

            // The chosen section and every section above it; one already in
            // the chain ends the walk, so a cycle in the data cannot hang it.
            $chain = [];
            for ($id = $value; array_key_exists($id, $parents) && ! in_array($id, $chain); $id = $parents[$id]) {
                $chain[] = $id;
            }

            if (in_array($record->getKey(), $chain)) {
                $fail(__('atrium::fields.validation.own_ancestor'));
            }
        };
    }
}

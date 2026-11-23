<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Support\Str;

class BelongsToManyField extends BelongsToField
{
    protected int $maxItems = 0;

    protected int $minItems = 0;

    protected bool $sortable = false;

    public function getType(): string
    {
        return 'belongsToMany';
    }

    /**
     * Set max items
     */
    public function maxItems(int $max): static
    {
        $this->maxItems = $max;

        return $this;
    }

    /**
     * Set min items
     */
    public function minItems(int $min): static
    {
        $this->minItems = $min;

        return $this;
    }

    /**
     * Enable/disable sorting
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * The records the edited one links to, through the relation named like
     * the field (`related_posts` → relatedPosts()).
     *
     * @return array<int,mixed>
     */
    protected function currentValues(): array
    {
        $relation = Str::camel($this->name);

        if ($this->record === null || ! method_exists($this->record, $relation)) {
            return [];
        }

        return $this->record->{$relation}->pluck($this->valueColumn)->all();
    }

    protected function getFieldProps(): array
    {
        return array_merge(parent::getFieldProps(), [
            'model' => $this->modelClass ?? null,
            'multiple' => true,
            'maxItems' => $this->maxItems,
            'minItems' => $this->minItems,
            'sortable' => $this->sortable,
        ]);
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        // Deliberately NOT parent::getValidationRules(): BelongsToField appends
        // `exists:table,id`, which cannot hold for an array of ids. Going
        // through the base keeps an explicit ->rules() working, which the old
        // hard-coded ['array'] did not.
        $rules = $this->baseValidationRules($context);
        $rules[] = 'array';

        if ($this->minItems > 0) {
            $rules[] = "min:{$this->minItems}";
        }

        if ($this->maxItems > 0) {
            $rules[] = "max:{$this->maxItems}";
        }

        return $rules;
    }

    public function transformValue(mixed $value): mixed
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}

<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class MultiSelectField extends SelectField
{
    protected int $maxItems = 0;

    protected int $minItems = 0;

    public function getType(): string
    {
        return 'multiSelect';
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

    protected function getFieldProps(): array
    {
        return array_merge(parent::getFieldProps(), [
            'multiple' => true,
            'maxItems' => $this->maxItems,
            'minItems' => $this->minItems,
        ]);
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        // Deliberately NOT parent::getValidationRules(): SelectField appends an
        // `in:` rule for a scalar. Going through the base keeps an explicit
        // ->rules() working, which the old hard-coded ['array'] did not.
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

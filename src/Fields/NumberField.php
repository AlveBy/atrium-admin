<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class NumberField extends Field
{
    protected ?float $min = null;

    protected ?float $max = null;

    protected float $step = 1;

    protected int $decimals = 0;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    public function getType(): string
    {
        return 'number';
    }

    /**
     * Set min value
     */
    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    /**
     * Set max value
     */
    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Set step
     */
    public function step(float $step): static
    {
        $this->step = $step;

        return $this;
    }

    /**
     * Set decimal places
     */
    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;
        $this->step = 1 / pow(10, $decimals);

        return $this;
    }

    /**
     * Set prefix
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set suffix
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Configure as money field
     */
    public function money(string $currency = '₽'): static
    {
        $this->decimals = 2;
        $this->suffix = $currency;
        $this->min = 0;

        return $this;
    }

    /**
     * Configure as percentage
     */
    public function percentage(): static
    {
        $this->suffix = '%';
        $this->min = 0;
        $this->max = 100;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'decimals' => $this->decimals,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'numeric';

        if ($this->min !== null) {
            $rules[] = "min:{$this->min}";
        }

        if ($this->max !== null) {
            $rules[] = "max:{$this->max}";
        }

        return $rules;
    }

    public function transformValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $this->decimals);
    }
}

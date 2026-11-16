<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class CheckboxField extends Field
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    /**
     * The value written to the column when the checkbox is on.
     *
     * In Bitrix, flags like "Show on home page" are single-option lists drawn as
     * a checkbox, and the database holds the option code ('YES', 'Y'). Public
     * templates compare against exactly that (`$item->show_on_home === 'YES'`),
     * so the admin checkbox must store the string, not a boolean.
     */
    protected ?string $checkedValue = null;

    public function getType(): string
    {
        return 'checkbox';
    }

    /**
     * Set label when checked
     */
    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    /**
     * Set label when unchecked
     */
    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    /**
     * Set both labels
     */
    public function labels(string $trueLabel, string $falseLabel): static
    {
        $this->trueLabel = $trueLabel;
        $this->falseLabel = $falseLabel;

        return $this;
    }

    /**
     * Store a specific string (the Bitrix option code) instead of a boolean.
     */
    public function checkedValue(string $value): static
    {
        $this->checkedValue = $value;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'trueLabel' => $this->trueLabel,
            'falseLabel' => $this->falseLabel,
            'checkedValue' => $this->checkedValue,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        // With a string value the request carries 'YES' or null — the boolean
        // rule would reject that.
        if ($this->checkedValue === null) {
            $rules[] = 'boolean';
        }

        return $rules;
    }

    public function transformValue(mixed $value): mixed
    {
        if ($this->checkedValue !== null) {
            return $value ? $this->checkedValue : null;
        }

        return (bool) $value;
    }
}

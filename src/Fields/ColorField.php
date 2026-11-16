<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class ColorField extends Field
{
    protected array $presetColors = [];

    protected bool $allowCustom = true;

    protected string $format = 'hex';

    public function getType(): string
    {
        return 'color';
    }

    /**
     * Set preset colors
     */
    public function presetColors(array $colors): static
    {
        $this->presetColors = $colors;

        return $this;
    }

    /**
     * Allow/disallow custom color input
     */
    public function allowCustom(bool $allow = true): static
    {
        $this->allowCustom = $allow;

        return $this;
    }

    /**
     * Set color format (hex, rgb, hsl)
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'presetColors' => $this->presetColors,
            'allowCustom' => $this->allowCustom,
            'format' => $this->format,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'string';

        if ($this->format === 'hex') {
            $rules[] = 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        }

        return $rules;
    }
}

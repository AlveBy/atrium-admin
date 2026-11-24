<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Fields\Concerns\AssistedByAi;

class TextField extends Field
{
    use AssistedByAi;

    protected int $maxLength = 255;

    protected ?string $inputType = 'text';

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    /**
     * Whether maxLength() was called, as opposed to the 255 default.
     *
     * The default is the HTML maxlength hint, not a schema fact: plenty of
     * TextFields sit on TEXT columns (static_pages.meta_keywords holds 279
     * characters today). Enforcing 255 on those would make existing rows
     * unsavable, so an unset maxLength falls back to the TEXT ceiling.
     */
    protected bool $maxLengthExplicit = false;

    public function getType(): string
    {
        return 'text';
    }

    /**
     * Set max length
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;
        $this->maxLengthExplicit = true;

        return $this;
    }

    /**
     * The longest value that is saved: the one set with maxLength(), or the
     * TEXT ceiling when none was set (see $maxLengthExplicit).
     */
    public function getMaxLength(): int
    {
        return $this->maxLengthExplicit ? $this->maxLength : 65535;
    }

    /**
     * Set input type (text, email, tel, url)
     */
    public function inputType(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    /**
     * Set as email input
     */
    public function email(): static
    {
        $this->inputType = 'email';
        $this->rules[] = 'email';

        return $this;
    }

    /**
     * Set as URL input
     */
    public function url(): static
    {
        $this->inputType = 'url';
        $this->rules[] = 'url';

        return $this;
    }

    /**
     * Set as tel input
     */
    public function tel(): static
    {
        $this->inputType = 'tel';

        return $this;
    }

    /**
     * Set as password input
     */
    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    /**
     * Whether the input hides what is typed: a password, a key.
     */
    public function isPassword(): bool
    {
        return $this->inputType === 'password';
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

    protected function getFieldProps(): array
    {
        return [
            'maxLength' => $this->maxLength,
            'inputType' => $this->inputType,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'string';
        $rules[] = 'max:'.$this->getMaxLength();

        return $rules;
    }
}

<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class TextField extends Field
{
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
        $rules[] = $this->maxLengthExplicit ? "max:{$this->maxLength}" : 'max:65535';

        return $rules;
    }
}

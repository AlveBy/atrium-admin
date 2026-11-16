<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class TextareaField extends Field
{
    protected int $rows = 4;

    protected ?int $maxLength = null;

    protected bool $autoResize = false;

    public function getType(): string
    {
        return 'textarea';
    }

    /**
     * Set number of rows
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Set max length
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Enable auto resize
     */
    public function autoResize(bool $autoResize = true): static
    {
        $this->autoResize = $autoResize;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'rows' => $this->rows,
            'maxLength' => $this->maxLength,
            'autoResize' => $this->autoResize,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'string';

        if ($this->maxLength) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}

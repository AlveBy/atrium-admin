<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class CheckboxField extends Field
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    /**
     * Значение, которое пишется в колонку при включённой галочке.
     *
     * В Битриксе «Забронировать», «Задать вопрос», «Выводить на главной» —
     * это списки из одного значения, показанные флажком, а в базе лежит код
     * варианта ('YES', 'Y'). Публичные шаблоны сравниваются именно с ним
     * (`$item->form_order === 'YES'`), поэтому галочка в админке должна
     * сохранять строку, а не boolean.
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
     * Хранить не boolean, а конкретную строку (код варианта из Битрикса).
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

        // Со строковым значением приходит 'YES' либо null — правило boolean
        // такую отправку отклонило бы.
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

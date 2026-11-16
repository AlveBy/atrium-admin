<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class DateField extends Field
{
    protected ?string $minDate = null;

    protected ?string $maxDate = null;

    protected string $format = 'Y-m-d';

    protected string $displayFormat = 'd.m.Y';

    public function getType(): string
    {
        return 'date';
    }

    /**
     * Set min date
     */
    public function minDate(string $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /**
     * Set max date
     */
    public function maxDate(string $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /**
     * Set min to today
     */
    public function minToday(): static
    {
        $this->minDate = 'today';

        return $this;
    }

    /**
     * Set max to today
     */
    public function maxToday(): static
    {
        $this->maxDate = 'today';

        return $this;
    }

    /**
     * Set storage format
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set display format
     */
    public function displayFormat(string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'minDate' => $this->minDate,
            'maxDate' => $this->maxDate,
            'format' => $this->format,
            'displayFormat' => $this->displayFormat,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'date';

        if ($this->minDate) {
            $rules[] = "after_or_equal:{$this->minDate}";
        }

        if ($this->maxDate) {
            $rules[] = "before_or_equal:{$this->maxDate}";
        }

        return $rules;
    }
}

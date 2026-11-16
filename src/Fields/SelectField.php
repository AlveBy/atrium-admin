<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Validation\Rule;

class SelectField extends Field
{
    protected array $options = [];

    protected bool $searchable = false;

    protected bool $clearable = true;

    protected ?string $optionLabel = 'label';

    protected ?string $optionValue = 'value';

    public function getType(): string
    {
        return 'select';
    }

    /**
     * Set options
     */
    public function options(array $options): static
    {
        $this->options = $this->normalizeOptions($options);

        return $this;
    }

    /**
     * Set options from enum
     */
    public function fromEnum(string $enumClass): static
    {
        if (! enum_exists($enumClass)) {
            return $this;
        }

        $options = [];
        foreach ($enumClass::cases() as $case) {
            $options[] = [
                'value' => $case->value ?? $case->name,
                'label' => method_exists($case, 'label') ? $case->label() : $case->name,
            ];
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Set options from model
     */
    public function fromModel(string $modelClass, string $labelColumn = 'name', string $valueColumn = 'id'): static
    {
        $this->attributes['modelClass'] = $modelClass;
        $this->attributes['labelColumn'] = $labelColumn;
        $this->attributes['valueColumn'] = $valueColumn;

        return $this;
    }

    /**
     * Enable search
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Enable/disable clear button
     */
    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    /**
     * Set option label key
     */
    public function optionLabel(string $key): static
    {
        $this->optionLabel = $key;

        return $this;
    }

    /**
     * Set option value key
     */
    public function optionValue(string $key): static
    {
        $this->optionValue = $key;

        return $this;
    }

    /**
     * Normalize options array
     */
    protected function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $normalized[] = $value;
            } else {
                $normalized[] = [
                    'value' => $key,
                    'label' => $value,
                ];
            }
        }

        return $normalized;
    }

    protected function getFieldProps(): array
    {
        return [
            'options' => $this->options,
            'searchable' => $this->searchable,
            'clearable' => $this->clearable,
            'optionLabel' => $this->optionLabel,
            'optionValue' => $this->optionValue,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        if (! empty($this->options)) {
            $values = array_column($this->options, 'value');

            // Migrated rows can hold a value the option list no longer declares
            // (e.g. static_pages.template = 'price' while the field offers only
            // default/contacts). Rejecting it would make the record impossible
            // to edit at all, so the stored value is tolerated while any *new*
            // value outside the list is still rejected.
            $stored = $context?->record?->getAttribute($this->getColumn());
            if ($stored !== null && $stored !== '' && is_scalar($stored) && ! in_array($stored, $values)) {
                $values[] = $stored;
            }

            // Rule::in quotes each value, so options containing a comma (or a
            // pipe) survive; 'in:' . implode(',') silently split them.
            $rules[] = Rule::in($values);
        }

        return $rules;
    }
}

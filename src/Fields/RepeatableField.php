<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class RepeatableField extends Field
{
    protected string $itemType = 'text';

    protected int $maxItems = 0; // 0 = unlimited

    protected int $minItems = 0;

    /** null — the generic "Add" in the current locale. */
    protected ?string $addLabel = null;

    protected string $itemLabel = '';

    /** @var array<int,string> modes for itemType('content') */
    protected array $itemModes = ['text', 'html', 'wysiwyg'];

    /**
     * Editor buttons for itemType('content').
     *
     * Kept the same set as ContentField: a multiple HTML property is the same kind
     * of property, and a trimmed toolbar there was not a decision but a consequence
     * of the button list simply not being passed through.
     *
     * @var array<int,string>
     */
    protected array $itemToolbar = ['bold', 'italic', 'underline', 'strike', 'color', 'link', 'bulletList', 'orderedList', 'align', 'heading', 'fontSize', 'image', 'file', 'table'];

    public function getType(): string
    {
        return 'repeatable';
    }

    /**
     * Set the type of each item (text, number, date, datetime, content).
     *
     * 'content' gives every item a full editor with the Text/HTML/Visual
     * switch — that is how multiple HTML properties looked in Bitrix.
     */
    public function itemType(string $type): static
    {
        $this->itemType = $type;

        return $this;
    }

    /**
     * Label of a single item: "Day description #1", "Departure date #2".
     */
    public function itemLabel(string $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    /**
     * Available editor modes for itemType('content').
     *
     * @param  array<int,string>  $modes
     */
    public function itemModes(array $modes): static
    {
        $this->itemModes = $modes;

        return $this;
    }

    /**
     * Editor buttons for itemType('content').
     *
     * @param  array<int,string>  $toolbar
     */
    public function itemToolbar(array $toolbar): static
    {
        $this->itemToolbar = $toolbar;

        return $this;
    }

    /**
     * Set maximum number of items
     */
    public function maxItems(int $max): static
    {
        $this->maxItems = $max;

        return $this;
    }

    /**
     * Set minimum number of items
     */
    public function minItems(int $min): static
    {
        $this->minItems = $min;

        return $this;
    }

    /**
     * Set label for add button
     */
    public function addLabel(string $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'itemType' => $this->itemType,
            'maxItems' => $this->maxItems,
            'minItems' => $this->minItems,
            'addLabel' => $this->addLabel ?? __('atrium::common.add'),
            'itemLabel' => $this->itemLabel,
            'itemModes' => $this->itemModes,
            'itemToolbar' => $this->itemToolbar,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'array';

        if ($this->maxItems > 0) {
            $rules[] = "max:{$this->maxItems}";
        }

        if ($this->minItems > 0) {
            $rules[] = "min:{$this->minItems}";
        }

        return $rules;
    }

    /**
     * Transform value before saving
     */
    public function transformValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        // Filter out empty values
        return array_values(array_filter($value, fn ($v) => $v !== '' && $v !== null));
    }
}

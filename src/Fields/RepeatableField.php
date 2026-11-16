<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class RepeatableField extends Field
{
    protected string $itemType = 'text';

    protected int $maxItems = 0; // 0 = без ограничения

    protected int $minItems = 0;

    protected string $addLabel = 'Добавить';

    protected string $itemLabel = '';

    /** @var array<int,string> режимы для itemType('content') */
    protected array $itemModes = ['text', 'html', 'wysiwyg'];

    /**
     * Кнопки редактора для itemType('content').
     *
     * Держим тем же набором, что у ContentField: «Описание дней» — такое же
     * HTML-свойство, и урезанная панель у него была не решением, а следствием
     * того, что список кнопок туда просто не передавали.
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
     * 'content' рисует у каждого элемента полноценный редактор с
     * переключателем Текст/HTML/Визуальный — так в Битриксе выглядели
     * множественные HTML-свойства вроде «Описание дней».
     */
    public function itemType(string $type): static
    {
        $this->itemType = $type;

        return $this;
    }

    /**
     * Подпись одного элемента: «Описание дня #1», «Дата отправления #2».
     */
    public function itemLabel(string $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    /**
     * Доступные режимы редактора для itemType('content').
     *
     * @param  array<int,string>  $modes
     */
    public function itemModes(array $modes): static
    {
        $this->itemModes = $modes;

        return $this;
    }

    /**
     * Кнопки редактора для itemType('content').
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
            'addLabel' => $this->addLabel,
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

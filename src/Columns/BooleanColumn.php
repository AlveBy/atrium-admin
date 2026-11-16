<?php

declare(strict_types=1);

namespace Atrium\Admin\Columns;

class BooleanColumn extends Column
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    protected string $trueColor = 'green';

    protected string $falseColor = 'red';

    protected bool $asIcon = true;

    public function getType(): string
    {
        return 'boolean';
    }

    /**
     * Set labels
     */
    public function labels(string $trueLabel, string $falseLabel): static
    {
        $this->trueLabel = $trueLabel;
        $this->falseLabel = $falseLabel;

        return $this;
    }

    /**
     * Set colors
     */
    public function colors(string $trueColor, string $falseColor): static
    {
        $this->trueColor = $trueColor;
        $this->falseColor = $falseColor;

        return $this;
    }

    /**
     * Display as icon
     */
    public function asIcon(bool $asIcon = true): static
    {
        $this->asIcon = $asIcon;

        return $this;
    }

    /**
     * Display as text
     */
    public function asText(): static
    {
        $this->asIcon = false;

        return $this;
    }

    protected function getColumnProps(): array
    {
        return [
            'trueLabel' => $this->trueLabel ?? __('atrium::common.yes'),
            'falseLabel' => $this->falseLabel ?? __('atrium::common.no'),
            'trueColor' => $this->trueColor,
            'falseColor' => $this->falseColor,
            'asIcon' => $this->asIcon,
        ];
    }

    public function formatValue(mixed $value, $model): mixed
    {
        return (bool) $value;
    }
}

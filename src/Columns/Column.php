<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Columns;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @phpstan-consistent-constructor */
abstract class Column implements Arrayable, JsonSerializable
{
    protected string $name;

    protected string $label = '';

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected ?string $width = null;

    protected ?string $align = null;

    protected bool $visible = true;

    /**
     * Create a new column instance
     */
    public static function make(string $name): static
    {
        $column = new static;
        $column->name = $name;

        return $column;
    }

    /**
     * Set the column label
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Enable sorting
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Enable searching
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Set column width
     */
    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Set column alignment
     */
    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Center align
     */
    public function alignCenter(): static
    {
        $this->align = 'center';

        return $this;
    }

    /**
     * Right align
     */
    public function alignRight(): static
    {
        $this->align = 'right';

        return $this;
    }

    /**
     * Set visibility
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Hide column
     */
    public function hidden(): static
    {
        $this->visible = false;

        return $this;
    }

    /**
     * Get the column name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the column type
     */
    abstract public function getType(): string;

    /**
     * Get the Vue component name
     */
    public function getComponent(): string
    {
        return $this->getType().'Column';
    }

    /**
     * Format value for display
     */
    public function formatValue(mixed $value, $model): mixed
    {
        return $value;
    }

    /**
     * Get additional column props
     */
    protected function getColumnProps(): array
    {
        return [];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_merge([
            'name' => $this->name,
            'type' => $this->getType(),
            'component' => $this->getComponent(),
            'label' => $this->label,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'width' => $this->width,
            'align' => $this->align,
            'visible' => $this->visible,
        ], $this->getColumnProps());
    }

    /**
     * Convert to JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

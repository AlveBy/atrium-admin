<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Columns;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
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

    protected ?string $permission = null;

    /** @var (Closure(Model): mixed)|null */
    protected ?Closure $value = null;

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
     * Shown only to those who have it: a bare action ("view") is the
     * module's own, a full name ("orders.view") is taken as it is. A column
     * another module adds with its own data needs its module's permission.
     */
    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * The permission it needs in a module, or null for anyone who sees the list.
     */
    public function permissionIn(string $module): ?string
    {
        return $this->permission === null || str_contains($this->permission, '.') ? $this->permission : "{$module}.{$this->permission}";
    }

    /**
     * Where the value comes from when it is not the record's attribute of
     * the column's name: another module's column in one hook, without
     * `atrium.record.present`.
     *
     * @param  Closure(Model): mixed  $value
     */
    public function value(Closure $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Whether value() gives the column its value.
     */
    public function isComputed(): bool
    {
        return $this->value !== null;
    }

    /**
     * The value of a record, from value().
     */
    public function valueOf(Model $record): mixed
    {
        return ($this->value)($record);
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

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Filters;

use Atrium\Admin\Facades\Hook;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A filter above a module's list: a module returns it from
 * getAdministrationFilters(), other modules add theirs through the
 * `atrium.module.filters` hook. The list, its export and the API's
 * `filter[name]=` narrow the records by it alike.
 *
 * Without query() a filter is a condition on the column of its name, and
 * `atrium.query.filter` may take it over (custom fields do).
 *
 * @implements Arrayable<string,mixed>
 */
abstract class Filter implements Arrayable
{
    protected string $label = '';

    protected ?string $permission = null;

    /** @var (Closure(Builder<covariant Model>, string): mixed)|null */
    protected ?Closure $query = null;

    final private function __construct(protected readonly string $name) {}

    /**
     * The name is the filter's key in the address: `?brand=4`.
     */
    final public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Shown and applied only to those who have it: a bare action ("view")
     * is the module's own, a full name ("orders.view") is taken as it is.
     */
    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * How the filter narrows the records: gets the query and the chosen
     * value, a string.
     *
     * @param  Closure(Builder<covariant Model>, string): mixed  $query
     */
    public function query(Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The permission it needs in a module, or null for anyone who sees the list.
     */
    public function permissionIn(string $module): ?string
    {
        return $this->permission === null || str_contains($this->permission, '.') ? $this->permission : "{$module}.{$this->permission}";
    }

    /**
     * `select` or `boolean`: how the panel draws it.
     */
    abstract public function type(): string;

    /**
     * The value from the address as the filter's own, or null when it is
     * none of its values: then the filter is left out.
     */
    abstract public function value(mixed $value): ?string;

    /**
     * Narrow the query by a value that value() accepted.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function apply(Builder $query, string $value): void
    {
        if ($this->query !== null) {
            ($this->query)($query, $value);
        } elseif (! Hook::apply('atrium.query.filter', false, $query, $this->name, $value)) {
            $this->where($query, $value);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'type' => $this->type(), 'label' => $this->label, 'options' => $this->choices()];
    }

    /**
     * The condition without query() and without a hook that takes it over.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    abstract protected function where(Builder $query, string $value): void;

    /**
     * What the panel offers to choose, in order: value and label.
     *
     * @return array<int,array{value:string,label:string}>
     */
    abstract protected function choices(): array;
}

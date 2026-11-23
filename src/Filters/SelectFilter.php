<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * One of a list of values: `SelectFilter::make('brand_id')->options(fn () =>
 * Brand::query()->pluck('name', 'id')->all())`. A value that is not among
 * the options is ignored.
 */
final class SelectFilter extends Filter
{
    /** @var array<int|string,string>|(Closure(): array<int|string,string>) */
    private array|Closure $options = [];

    /**
     * Value => label, in the order shown; a closure is read when the list
     * is drawn, not when the module is registered.
     *
     * @param  array<int|string,string>|(Closure(): array<int|string,string>)  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    public function value(mixed $value): ?string
    {
        return is_scalar($value) && in_array((string) $value, array_column($this->choices(), 'value'), true) ? (string) $value : null;
    }

    protected function where(Builder $query, string $value): void
    {
        $query->where($this->name, $value);
    }

    protected function choices(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;
        $list = [];

        foreach ($options as $value => $label) {
            $list[] = ['value' => (string) $value, 'label' => $label];
        }

        return $list;
    }
}

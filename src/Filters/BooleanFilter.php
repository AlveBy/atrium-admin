<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Yes or no: `BooleanFilter::make('active')`. The value is `1` or `0`; the
 * panel calls them "Yes" and "No" in the administrator's language.
 */
final class BooleanFilter extends Filter
{
    public function type(): string
    {
        return 'boolean';
    }

    public function value(mixed $value): ?string
    {
        return match ($value) {
            '1', 1, true => '1',
            '0', 0, false => '0',
            default => null,
        };
    }

    protected function where(Builder $query, string $value): void
    {
        $query->where($this->name, $value === '1');
    }

    protected function choices(): array
    {
        return [
            ['value' => '1', 'label' => __('atrium::resource.filters.yes')],
            ['value' => '0', 'label' => __('atrium::resource.filters.no')],
        ];
    }
}

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Columns;

use Carbon\Carbon;

class DateColumn extends Column
{
    protected string $format = 'd.m.Y';

    protected bool $diffForHumans = false;

    protected ?string $timezone = null;

    public function getType(): string
    {
        return 'date';
    }

    /**
     * Set date format
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Display datetime
     */
    public function datetime(): static
    {
        $this->format = 'd.m.Y H:i';

        return $this;
    }

    /**
     * Display time only
     */
    public function time(): static
    {
        $this->format = 'H:i';

        return $this;
    }

    /**
     * Display relative time
     */
    public function diffForHumans(bool $diff = true): static
    {
        $this->diffForHumans = $diff;

        return $this;
    }

    /**
     * Set timezone
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    protected function getColumnProps(): array
    {
        return [
            'format' => $this->format,
            'diffForHumans' => $this->diffForHumans,
            'timezone' => $this->timezone,
        ];
    }

    public function formatValue(mixed $value, $model): mixed
    {
        if (! $value) {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        if ($this->timezone) {
            $date = $date->setTimezone($this->timezone);
        }

        if ($this->diffForHumans) {
            return $date->diffForHumans();
        }

        return $date->format($this->format);
    }
}

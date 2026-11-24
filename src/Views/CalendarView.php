<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

/**
 * Records on a calendar: `CalendarView::make('starts_at')` puts each record
 * on the day of its DateField or DateTimeField; `until('ends_at')` makes a
 * record last until the date of a second field. Dragged to another day, a
 * record keeps its time and its length and saves the new dates the way the
 * form does.
 *
 *     CalendarView::make('active_from')->title('name')
 */
final class CalendarView extends View
{
    protected ?string $until = null;

    /**
     * The field with the end of the period; a record without it is one day.
     */
    public function until(string $field): static
    {
        $this->until = $field;

        return $this;
    }

    public function getUntil(): ?string
    {
        return $this->until;
    }

    public function type(): string
    {
        return 'calendar';
    }

    public function writes(): array
    {
        return $this->until === null ? [$this->field] : [$this->field, $this->until];
    }

    public function needs(): array
    {
        return [
            $this->field => ['date', 'datetime'],
            ...($this->until === null ? [] : [$this->until => ['date', 'datetime']]),
        ];
    }
}

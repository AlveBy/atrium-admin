<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The ways to look at a module's list besides the table: the switcher, the
 * kanban board and the calendar.
 */

return [
    'label' => 'List view',
    'table' => 'Table',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'No value',
        'count' => 'Cards: :count',
        'empty' => 'No cards',
        'more' => 'Show more',
        'move_to' => 'Move to column',
        'failed' => 'The card could not be moved.',
    ],

    'calendar' => [
        'name' => 'Calendar',
        'month' => 'Month',
        'week' => 'Week',
        'list' => 'List',
        'modes' => 'Calendar mode',
        'today' => 'Today',
        'previous' => 'Previous',
        'next' => 'Next',
        'add' => 'Add on :date',
        'empty' => 'Nothing on these days',
        'truncated' => 'Only the first :count records are shown. Narrow the list with the search or a filter.',
        'no_date' => 'The record has no date, so it cannot be moved.',
        'failed' => 'The record could not be moved.',
    ],
];

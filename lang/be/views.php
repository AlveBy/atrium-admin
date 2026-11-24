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
    'label' => 'Выгляд спісу',
    'table' => 'Табліца',

    'kanban' => [
        'name' => 'Канбан',
        'no_value' => 'Без значэння',
        'count' => 'Картак: :count',
        'empty' => 'Картак няма',
        'more' => 'Паказаць яшчэ',
        'move_to' => 'Перанесці ў калонку',
        'failed' => 'Не ўдалося перанесці картку.',
    ],

    'calendar' => [
        'name' => 'Каляндар',
        'month' => 'Месяц',
        'week' => 'Тыдзень',
        'list' => 'Спіс',
        'modes' => 'Рэжым каляндара',
        'today' => 'Сёння',
        'previous' => 'Назад',
        'next' => 'Далей',
        'add' => 'Дадаць на :date',
        'empty' => 'У гэтыя дні нічога няма',
        'truncated' => 'Паказаны толькі першыя :count запісаў. Звузьце спіс пошукам або фільтрам.',
        'no_date' => 'У запісу няма даты, таму яго нельга перанесці.',
        'failed' => 'Не ўдалося перанесці запіс.',
    ],
];

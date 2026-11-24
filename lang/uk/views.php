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
    'label' => 'Вигляд списку',
    'table' => 'Таблиця',

    'kanban' => [
        'name' => 'Канбан',
        'no_value' => 'Без значення',
        'count' => 'Карток: :count',
        'empty' => 'Карток немає',
        'more' => 'Показати ще',
        'move_to' => 'Перенести до колонки',
        'failed' => 'Не вдалося перенести картку.',
    ],

    'calendar' => [
        'name' => 'Календар',
        'month' => 'Місяць',
        'week' => 'Тиждень',
        'list' => 'Список',
        'modes' => 'Режим календаря',
        'today' => 'Сьогодні',
        'previous' => 'Назад',
        'next' => 'Вперед',
        'add' => 'Додати на :date',
        'empty' => 'У ці дні нічого немає',
        'truncated' => 'Показано лише перші :count записів. Звузьте список пошуком або фільтром.',
        'no_date' => 'У запису немає дати, тому його не можна перенести.',
        'move_to' => 'Перенести на день',
        'failed' => 'Не вдалося перенести запис.',
    ],
];

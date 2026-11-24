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
    'label' => 'Вид списка',
    'table' => 'Таблица',

    'kanban' => [
        'name' => 'Канбан',
        'no_value' => 'Без значения',
        'count' => 'Карточек: :count',
        'empty' => 'Карточек нет',
        'more' => 'Показать ещё',
        'move_to' => 'Перенести в колонку',
        'failed' => 'Не удалось перенести карточку.',
    ],

    'calendar' => [
        'name' => 'Календарь',
        'month' => 'Месяц',
        'week' => 'Неделя',
        'list' => 'Список',
        'modes' => 'Режим календаря',
        'today' => 'Сегодня',
        'previous' => 'Назад',
        'next' => 'Вперёд',
        'add' => 'Добавить на :date',
        'empty' => 'В эти дни ничего нет',
        'truncated' => 'Показаны только первые :count записей. Сузьте список поиском или фильтром.',
        'no_date' => 'У записи нет даты, поэтому её нельзя перенести.',
        'failed' => 'Не удалось перенести запись.',
    ],
];

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
    'label' => 'Тізім көрінісі',
    'table' => 'Кесте',

    'kanban' => [
        'name' => 'Канбан',
        'no_value' => 'Мәнсіз',
        'count' => 'Карточкалар: :count',
        'empty' => 'Карточкалар жоқ',
        'more' => 'Тағы көрсету',
        'move_to' => 'Бағанға жылжыту',
        'failed' => 'Карточканы жылжыту мүмкін болмады.',
    ],

    'calendar' => [
        'name' => 'Күнтізбе',
        'month' => 'Ай',
        'week' => 'Апта',
        'list' => 'Тізім',
        'modes' => 'Күнтізбе режимі',
        'today' => 'Бүгін',
        'previous' => 'Артқа',
        'next' => 'Алға',
        'add' => ':date күніне қосу',
        'empty' => 'Бұл күндері ештеңе жоқ',
        'truncated' => 'Тек алғашқы :count жазба көрсетілген. Тізімді іздеумен немесе сүзгімен тарылтыңыз.',
        'no_date' => 'Жазбада күн жоқ, сондықтан оны жылжыту мүмкін емес.',
        'move_to' => 'Күнге жылжыту',
        'failed' => 'Жазбаны жылжыту мүмкін болмады.',
    ],
];

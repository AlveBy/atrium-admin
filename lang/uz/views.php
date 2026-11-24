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
    'label' => 'Ro‘yxat ko‘rinishi',
    'table' => 'Jadval',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Qiymatsiz',
        'count' => 'Kartochkalar: :count',
        'empty' => 'Kartochkalar yo‘q',
        'more' => 'Yana ko‘rsatish',
        'move_to' => 'Ustunga ko‘chirish',
        'failed' => 'Kartochkani ko‘chirib bo‘lmadi.',
    ],

    'calendar' => [
        'name' => 'Kalendar',
        'month' => 'Oy',
        'week' => 'Hafta',
        'list' => 'Ro‘yxat',
        'modes' => 'Kalendar rejimi',
        'today' => 'Bugun',
        'previous' => 'Oldingi',
        'next' => 'Keyingi',
        'add' => ':date kuniga qo‘shish',
        'empty' => 'Bu kunlarda hech narsa yo‘q',
        'truncated' => 'Faqat birinchi :count ta yozuv ko‘rsatilgan. Ro‘yxatni qidiruv yoki filtr bilan toraytiring.',
        'no_date' => 'Yozuvda sana yo‘q, shuning uchun uni ko‘chirib bo‘lmaydi.',
        'failed' => 'Yozuvni ko‘chirib bo‘lmadi.',
    ],
];

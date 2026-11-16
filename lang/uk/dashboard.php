<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Робочий стіл',

    'site_speed' => [
        'title' => 'Швидкість сайту',
        'no_data' => 'немає даних',
        'unit' => 'с',
        'bands' => [
            'very_fast' => 'Дуже швидко',
            'fast' => 'Швидко',
            'not_fast' => 'Не дуже швидко',
            'slow' => 'Повільно',
            'very_slow' => 'Дуже повільно',
        ],
        'samples_note' => 'медіана за :samples вимірюваннями за :days дн.',
        'no_samples' => 'вимірювань ще немає — показники з\'являться після перших відвідувань сайту',
    ],

    'performance' => [
        'title' => 'Монітор продуктивності',
        'details' => 'Докладніше',
        'collapse' => 'Згорнути',
        'caption' => 'Поточна оцінка',
        'unit' => 'сторінок за секунду',
        'pending' => 'Оцінка з\'явиться після перших відвідувань сайту',
        'note' => 'середній час генерації :seconds с · :pages стор. за :days дн.',
        'slowest_title' => 'Найважчі сторінки',
        'slowest_row' => ':ms мс · :hits',
    ],

    'recent' => [
        'title' => 'Останні елементи',
        'empty' => 'Елементів ще немає.',
        'all' => 'Усі елементи',
    ],
];

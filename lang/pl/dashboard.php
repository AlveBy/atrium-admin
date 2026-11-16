<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Pulpit',

    'site_speed' => [
        'title' => 'Szybkość witryny',
        'no_data' => 'brak danych',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Bardzo szybko',
            'fast' => 'Szybko',
            'not_fast' => 'Niezbyt szybko',
            'slow' => 'Wolno',
            'very_slow' => 'Bardzo wolno',
        ],
        'samples_note' => 'mediana z :samples pomiarów z :days dni',
        'no_samples' => 'brak pomiarów — liczby pojawią się po pierwszych odwiedzinach witryny',
    ],

    'performance' => [
        'title' => 'Monitor wydajności',
        'details' => 'Szczegóły',
        'collapse' => 'Zwiń',
        'caption' => 'Bieżący wynik',
        'unit' => 'stron na sekundę',
        'pending' => 'Wynik pojawi się po pierwszych odwiedzinach witryny',
        'note' => 'średni czas generowania :seconds s · :pages stron z :days dni',
        'slowest_title' => 'Najcięższe strony',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Ostatnie elementy',
        'empty' => 'Brak elementów.',
        'all' => 'Wszystkie elementy',
    ],
];

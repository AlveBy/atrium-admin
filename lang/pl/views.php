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
    'label' => 'Widok listy',
    'table' => 'Tabela',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Bez wartości',
        'count' => 'Karty: :count',
        'empty' => 'Brak kart',
        'more' => 'Pokaż więcej',
        'move_to' => 'Przenieś do kolumny',
        'failed' => 'Nie udało się przenieść karty.',
    ],

    'calendar' => [
        'name' => 'Kalendarz',
        'month' => 'Miesiąc',
        'week' => 'Tydzień',
        'list' => 'Lista',
        'modes' => 'Tryb kalendarza',
        'today' => 'Dziś',
        'previous' => 'Wstecz',
        'next' => 'Dalej',
        'add' => 'Dodaj na :date',
        'empty' => 'W te dni nic nie ma',
        'truncated' => 'Wyświetlono tylko pierwsze :count rekordów. Zawęź listę wyszukiwaniem lub filtrem.',
        'no_date' => 'Rekord nie ma daty, więc nie można go przenieść.',
        'failed' => 'Nie udało się przenieść rekordu.',
    ],
];

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
    'label' => 'Listenansicht',
    'table' => 'Tabelle',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Ohne Wert',
        'count' => 'Karten: :count',
        'empty' => 'Keine Karten',
        'more' => 'Mehr anzeigen',
        'move_to' => 'In Spalte verschieben',
        'failed' => 'Die Karte konnte nicht verschoben werden.',
    ],

    'calendar' => [
        'name' => 'Kalender',
        'month' => 'Monat',
        'week' => 'Woche',
        'list' => 'Liste',
        'modes' => 'Kalenderansicht',
        'today' => 'Heute',
        'previous' => 'Zurück',
        'next' => 'Weiter',
        'add' => 'Am :date hinzufügen',
        'empty' => 'An diesen Tagen ist nichts',
        'truncated' => 'Es werden nur die ersten :count Einträge angezeigt. Grenzen Sie die Liste mit der Suche oder einem Filter ein.',
        'no_date' => 'Der Eintrag hat kein Datum und kann deshalb nicht verschoben werden.',
        'move_to' => 'Auf Tag verschieben',
        'failed' => 'Der Eintrag konnte nicht verschoben werden.',
    ],
];

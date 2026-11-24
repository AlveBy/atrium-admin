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
    'label' => 'Vista dell’elenco',
    'table' => 'Tabella',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Senza valore',
        'count' => 'Schede: :count',
        'empty' => 'Nessuna scheda',
        'more' => 'Mostra altre',
        'move_to' => 'Sposta nella colonna',
        'failed' => 'Non è stato possibile spostare la scheda.',
    ],

    'calendar' => [
        'name' => 'Calendario',
        'month' => 'Mese',
        'week' => 'Settimana',
        'list' => 'Elenco',
        'modes' => 'Modalità del calendario',
        'today' => 'Oggi',
        'previous' => 'Precedente',
        'next' => 'Successivo',
        'add' => 'Aggiungi il :date',
        'empty' => 'Niente in questi giorni',
        'truncated' => 'Vengono mostrati solo i primi :count record. Restringi l’elenco con la ricerca o un filtro.',
        'no_date' => 'Il record non ha una data, quindi non può essere spostato.',
        'move_to' => 'Sposta al giorno',
        'failed' => 'Non è stato possibile spostare il record.',
    ],
];

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
    'label' => 'Vista de la lista',
    'table' => 'Tabla',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Sin valor',
        'count' => 'Tarjetas: :count',
        'empty' => 'No hay tarjetas',
        'more' => 'Mostrar más',
        'move_to' => 'Mover a la columna',
        'failed' => 'No se pudo mover la tarjeta.',
    ],

    'calendar' => [
        'name' => 'Calendario',
        'month' => 'Mes',
        'week' => 'Semana',
        'list' => 'Lista',
        'modes' => 'Modo del calendario',
        'today' => 'Hoy',
        'previous' => 'Anterior',
        'next' => 'Siguiente',
        'add' => 'Añadir el :date',
        'empty' => 'No hay nada estos días',
        'truncated' => 'Solo se muestran los primeros :count registros. Reduzca la lista con la búsqueda o un filtro.',
        'no_date' => 'El registro no tiene fecha, por lo que no se puede mover.',
        'failed' => 'No se pudo mover el registro.',
    ],
];

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
    'label' => 'Liste görünümü',
    'table' => 'Tablo',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Değer yok',
        'count' => 'Kart: :count',
        'empty' => 'Kart yok',
        'more' => 'Daha fazla göster',
        'move_to' => 'Sütuna taşı',
        'failed' => 'Kart taşınamadı.',
    ],

    'calendar' => [
        'name' => 'Takvim',
        'month' => 'Ay',
        'week' => 'Hafta',
        'list' => 'Liste',
        'modes' => 'Takvim modu',
        'today' => 'Bugün',
        'previous' => 'Önceki',
        'next' => 'Sonraki',
        'add' => ':date tarihine ekle',
        'empty' => 'Bu günlerde hiçbir şey yok',
        'truncated' => 'Yalnızca ilk :count kayıt gösteriliyor. Listeyi arama veya filtre ile daraltın.',
        'no_date' => 'Kaydın tarihi olmadığı için taşınamaz.',
        'move_to' => 'Güne taşı',
        'failed' => 'Kayıt taşınamadı.',
    ],
];

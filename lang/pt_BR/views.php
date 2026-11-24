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
    'label' => 'Visualização da lista',
    'table' => 'Tabela',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Sem valor',
        'count' => 'Cartões: :count',
        'empty' => 'Nenhum cartão',
        'more' => 'Mostrar mais',
        'move_to' => 'Mover para a coluna',
        'failed' => 'Não foi possível mover o cartão.',
    ],

    'calendar' => [
        'name' => 'Calendário',
        'month' => 'Mês',
        'week' => 'Semana',
        'list' => 'Lista',
        'modes' => 'Modo do calendário',
        'today' => 'Hoje',
        'previous' => 'Anterior',
        'next' => 'Próximo',
        'add' => 'Adicionar em :date',
        'empty' => 'Nada nestes dias',
        'truncated' => 'Somente os primeiros :count registros são exibidos. Reduza a lista com a busca ou um filtro.',
        'no_date' => 'O registro não tem data, por isso não pode ser movido.',
        'failed' => 'Não foi possível mover o registro.',
    ],
];

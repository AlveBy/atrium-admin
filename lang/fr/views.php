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
    'label' => 'Affichage de la liste',
    'table' => 'Tableau',

    'kanban' => [
        'name' => 'Kanban',
        'no_value' => 'Sans valeur',
        'count' => 'Cartes : :count',
        'empty' => 'Aucune carte',
        'more' => 'Afficher plus',
        'move_to' => 'Déplacer vers la colonne',
        'failed' => 'La carte n\'a pas pu être déplacée.',
    ],

    'calendar' => [
        'name' => 'Calendrier',
        'month' => 'Mois',
        'week' => 'Semaine',
        'list' => 'Liste',
        'modes' => 'Mode du calendrier',
        'today' => 'Aujourd\'hui',
        'previous' => 'Précédent',
        'next' => 'Suivant',
        'add' => 'Ajouter le :date',
        'empty' => 'Rien ces jours-ci',
        'truncated' => 'Seuls les :count premiers enregistrements sont affichés. Réduisez la liste avec la recherche ou un filtre.',
        'no_date' => 'L\'enregistrement n\'a pas de date, il ne peut donc pas être déplacé.',
        'move_to' => 'Déplacer au jour',
        'failed' => 'L\'enregistrement n\'a pas pu être déplacé.',
    ],
];

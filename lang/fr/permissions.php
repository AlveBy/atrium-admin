<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'Superadmin',
    'superadmin_hint' => 'Peut tout faire sans rôle et est le seul à gérer les rôles et les autres superadmins.',
    'roles' => 'Rôles',
    'roles_hint' => 'L’administrateur peut faire tout ce qu’autorise l’un des rôles cochés.',
    'forbidden' => 'Votre rôle ne le permet pas.',
    'last_superadmin' => 'C’est le dernier superadmin actif. Il faut que quelqu’un reste pour gérer les rôles.',
    'saved' => 'Rôle enregistré.',
    'deleted' => 'Rôle supprimé.',
    'create_title' => 'Nouveau rôle',
    'edit_title' => 'Rôle : :name',
    'name' => 'Nom',
    'permissions' => 'Droits',
    'section' => 'Section',
    'permissions_count' => 'Droits',
    'users_count' => 'Administrateurs',
    'select_all' => 'Tout cocher',
    'clear' => 'Tout décocher',
    'all' => 'Tous',
    'matrix_hint' => '« Voir » ouvre la liste et les fiches en lecture seule. Créer, modifier et supprimer en ont besoin aussi, il est donc coché avec eux.',
    'index_hint' => 'Un rôle est un ensemble de droits ; on attribue les rôles aux administrateurs sur leur page. Un superadmin n’a besoin d’aucun rôle : il peut tout faire.',
    'empty' => 'Aucun rôle pour l’instant.',
    'delete_title' => 'Supprimer le rôle ?',
    'delete_confirm' => 'les administrateurs ayant ce rôle perdront ce qu’il permettait.',

    'actions' => [
        'view' => 'Voir',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
        'use' => 'Émettre et utiliser des jetons',
    ],
];

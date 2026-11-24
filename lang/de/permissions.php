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
    'superadmin_hint' => 'Darf ohne Rollen alles und verwaltet als Einziger Rollen und andere Superadmins.',
    'roles' => 'Rollen',
    'roles_hint' => 'Der Administrator darf alles, was eine der angekreuzten Rollen erlaubt.',
    'site_roles' => 'Rollen auf einzelnen Websites',
    'site_roles_hint' => 'Gelten nur, solange diese Website im Panel gewählt ist, zusätzlich zu den Rollen oben: eine Website bearbeiten und eine andere nur ansehen.',
    'forbidden' => 'Ihre Rolle erlaubt das nicht.',
    'last_superadmin' => 'Das ist der letzte aktive Superadmin. Jemand muss bleiben, um die Rollen zu verwalten.',
    'saved' => 'Rolle gespeichert.',
    'deleted' => 'Rolle gelöscht.',
    'create_title' => 'Neue Rolle',
    'edit_title' => 'Rolle: :name',
    'name' => 'Name',
    'permissions' => 'Rechte',
    'section' => 'Bereich',
    'permissions_count' => 'Rechte',
    'users_count' => 'Administratoren',
    'select_all' => 'Alle auswählen',
    'clear' => 'Alle abwählen',
    'all' => 'Alle',
    'other' => 'Sonstiges',
    'matrix_hint' => '„Ansehen“ öffnet die Liste und die Einträge schreibgeschützt. Anlegen, Bearbeiten und Löschen brauchen es auch, daher wird es mit angekreuzt.',
    'index_hint' => 'Eine Rolle ist ein Satz von Rechten; Rollen bekommen Administratoren auf ihrer Seite. Superadmins brauchen keine Rolle: Sie dürfen alles.',
    'empty' => 'Noch keine Rollen.',
    'delete_title' => 'Rolle löschen?',
    'delete_confirm' => 'Administratoren mit dieser Rolle verlieren, was sie erlaubte.',

    'actions' => [
        'view' => 'Ansehen',
        'create' => 'Anlegen',
        'update' => 'Bearbeiten',
        'delete' => 'Löschen',
        'restore' => 'Aus dem Papierkorb wiederherstellen',
        'force_delete' => 'Endgültig löschen',
        'use' => 'Tokens ausstellen und nutzen',
        'settings' => 'Einstellungen',
        'import' => 'Import',
        'export' => 'Export',
    ],
];

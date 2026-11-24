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
    'superadmin_hint' => 'May do everything without roles and is the only one who manages roles and other superadmins.',
    'roles' => 'Roles',
    'roles_hint' => 'The administrator may do whatever any of these roles allows.',
    'site_roles' => 'Roles on one site',
    'site_roles_hint' => 'They count only while that site is chosen in the panel, on top of the roles above: edit one site and only look at another.',
    'forbidden' => 'Your role does not allow this.',
    'last_superadmin' => 'This is the last active superadmin. Someone has to be left to manage roles.',
    'saved' => 'Role saved.',
    'deleted' => 'Role deleted.',
    'create_title' => 'New role',
    'edit_title' => 'Role: :name',
    'name' => 'Name',
    'permissions' => 'Permissions',
    'section' => 'Section',
    'permissions_count' => 'Permissions',
    'users_count' => 'Administrators',
    'select_all' => 'Select all',
    'clear' => 'Clear',
    'all' => 'All',
    'other' => 'Other',
    'matrix_hint' => '"View" opens the list and the records, read-only. Creating, editing and deleting need it too, so it is ticked along with them.',
    'index_hint' => 'A role is a set of permissions; give roles to administrators on their page. Superadmins need no role: they may do everything.',
    'empty' => 'No roles yet.',
    'delete_title' => 'Delete the role?',
    'delete_confirm' => 'administrators with this role lose what it allowed.',

    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
        'restore' => 'Restore from the trash',
        'force_delete' => 'Delete for good',
        'use' => 'Issue and use tokens',
        'settings' => 'Settings',
        'import' => 'Import',
        'export' => 'Export',
    ],
];

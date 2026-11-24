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
    'superadmin_hint' => 'Puede hacerlo todo sin roles y es el único que gestiona los roles y a otros superadmins.',
    'roles' => 'Roles',
    'roles_hint' => 'El administrador puede hacer todo lo que permita cualquiera de los roles marcados.',
    'site_roles' => 'Roles en sitios concretos',
    'site_roles_hint' => 'Solo cuentan mientras ese sitio está elegido en el panel, además de los roles de arriba: editar un sitio y solo ver otro.',
    'forbidden' => 'Su rol no lo permite.',
    'last_superadmin' => 'Es el último superadmin activo. Alguien tiene que quedar para gestionar los roles.',
    'saved' => 'Rol guardado.',
    'deleted' => 'Rol eliminado.',
    'create_title' => 'Nuevo rol',
    'edit_title' => 'Rol: :name',
    'name' => 'Nombre',
    'permissions' => 'Permisos',
    'section' => 'Sección',
    'permissions_count' => 'Permisos',
    'users_count' => 'Administradores',
    'select_all' => 'Marcar todo',
    'clear' => 'Desmarcar todo',
    'all' => 'Todo',
    'other' => 'Otros',
    'matrix_hint' => '«Ver» abre la lista y los registros en solo lectura. Crear, editar y eliminar también lo necesitan, así que se marca junto con ellos.',
    'index_hint' => 'Un rol es un conjunto de permisos; los roles se asignan a los administradores en su página. Un superadmin no necesita rol: puede hacerlo todo.',
    'empty' => 'Todavía no hay roles.',
    'delete_title' => '¿Eliminar el rol?',
    'delete_confirm' => 'los administradores con este rol perderán lo que permitía.',

    'actions' => [
        'view' => 'Ver',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar de la papelera',
        'force_delete' => 'Eliminar definitivamente',
        'use' => 'Emitir y usar tokens',
        'settings' => 'Ajustes',
        'import' => 'Importación',
        'export' => 'Exportación',
    ],
];

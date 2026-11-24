<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Registro de cambios',
    'subtitle' => 'Quién cambió qué y cuándo, en todos los módulos que puede abrir.',
    'record_title' => 'Historial: :title',
    'button' => 'Historial',
    'empty' => 'Todavía no hay nada registrado.',
    'filters' => [
        'module' => 'Módulo',
        'admin' => 'Administrador',
        'from' => 'Desde',
        'to' => 'Hasta',
        'all' => 'Todos',
        'apply' => 'Aplicar',
        'reset' => 'Restablecer',
    ],
    'events' => [
        'created' => 'Creado',
        'updated' => 'Modificado',
        'deleted' => 'Eliminado',
        'restored' => 'Restaurado',
    ],
    'sources' => [
        'admin' => 'en el panel',
        'api' => 'vía API',
        'import' => 'por importación',
        'console' => 'por un comando',
        'schedule' => 'por el programador',
    ],
    'system' => 'Sistema',
    'field' => 'Campo',
    'old' => 'Antes',
    'new' => 'Ahora',
    'secret' => 'modificado (el valor no se guarda)',
    'empty_value' => 'vacío',
    'yes' => 'sí',
    'no' => 'no',
    'back' => 'Volver al registro',
    'versions' => [
        'title' => 'Versiones',
        'hint' => 'Lo que contenía el formulario tras cada guardado. Compare dos versiones o restaure una anterior.',
        'baseline' => 'Antes de empezar el historial',
        'current' => 'actual',
        'from' => 'Comparar',
        'to' => 'con',
        'compare' => 'Comparar',
        'same' => 'Las dos versiones son iguales.',
        'restore' => 'Restaurar',
        'confirm_title' => '¿Restaurar esta versión?',
        'confirm' => 'El registro recibe los valores de esta versión. Lo que contiene ahora se queda aquí como una versión más.',
        'restored' => 'La versión se ha restaurado.',
        'failed' => 'No se puede restaurar la versión: :errors',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The installed module packages and their settings.
 */
return [
    'title' => 'Módulos',
    'index_hint' => 'Los paquetes de módulos instalados en este sitio. Un módulo con ajustes tiene un botón para ellos.',
    'empty' => 'No hay paquetes de módulos instalados.',
    'module' => 'Módulo',
    'package' => 'Paquete',
    'settings' => 'Ajustes',
    'settings_title' => ':module: ajustes',
    'per_site' => 'Estos son los ajustes del sitio «:site». Elija otro sitio en la cabecera para configurar los suyos.',
    'saved' => 'Ajustes guardados.',
    'enable' => 'Activar',
    'disable' => 'Desactivar',
    'enabled' => ':module está activado.',
    'disabled' => ':module está desactivado. Sus datos y ajustes se conservan.',
    'parent_off' => 'Active primero :parent.',
    'config_locked' => 'No se pudo editar config/atrium.php.',
    'required_title' => 'Requeridos, sin instalar',
    'required_hint' => 'Paquetes de composer.json que aún no están instalados. Para instalar uno:',
    'catalogue_title' => 'Catálogo',
    'catalogue_hint' => 'Módulos de Atrium publicados en Packagist. Para añadir uno, ejecute en la carpeta del proyecto:',
    'catalogue_loading' => 'Cargando el catálogo…',
    'catalogue_unavailable' => 'Packagist no respondió. Inténtelo más tarde.',
    'catalogue_empty' => 'No se encontraron módulos en Packagist.',
    'status_installed' => 'Instalado',
    'status_disabled' => 'Desactivado',
    'status_required' => 'Requerido',
    'downloads' => ':count descargas',
];

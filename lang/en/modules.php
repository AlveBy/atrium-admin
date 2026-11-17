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
    'title' => 'Modules',
    'index_hint' => 'The module packages installed on this site. A module with settings has a button to them.',
    'empty' => 'No module packages are installed.',
    'module' => 'Module',
    'package' => 'Package',
    'settings' => 'Settings',
    'settings_title' => ':module: settings',
    'per_site' => 'These settings are for the site ":site". Choose another site in the header to set up its own.',
    'saved' => 'Settings saved.',
    'enable' => 'Switch on',
    'disable' => 'Switch off',
    'enabled' => ':module is switched on.',
    'disabled' => ':module is switched off. Its data and settings are kept.',
    'parent_off' => 'Switch on :parent first.',
    'config_locked' => 'config/atrium.php could not be edited.',
    'required_title' => 'Required, not installed',
    'required_hint' => 'Packages in composer.json that are not installed yet. Install one with:',
    'catalogue_title' => 'Catalogue',
    'catalogue_hint' => 'Atrium modules published on Packagist. To add one, run in the project folder:',
    'catalogue_loading' => 'Loading the catalogue…',
    'catalogue_unavailable' => 'Packagist did not answer. Try again later.',
    'catalogue_empty' => 'No modules found on Packagist.',
    'status_installed' => 'Installed',
    'status_disabled' => 'Switched off',
    'status_required' => 'Required',
    'downloads' => ':count downloads',
];

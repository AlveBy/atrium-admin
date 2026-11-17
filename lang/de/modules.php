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
    'title' => 'Module',
    'index_hint' => 'Die auf dieser Website installierten Modulpakete. Ein Modul mit Einstellungen hat eine Schaltfläche dorthin.',
    'empty' => 'Es sind keine Modulpakete installiert.',
    'module' => 'Modul',
    'package' => 'Paket',
    'settings' => 'Einstellungen',
    'settings_title' => ':module: Einstellungen',
    'per_site' => 'Dies sind die Einstellungen der Website „:site“. Wählen Sie oben eine andere Website, um deren eigene einzurichten.',
    'saved' => 'Einstellungen gespeichert.',
    'enable' => 'Einschalten',
    'disable' => 'Ausschalten',
    'enabled' => ':module ist eingeschaltet.',
    'disabled' => ':module ist ausgeschaltet. Daten und Einstellungen bleiben erhalten.',
    'parent_off' => 'Schalten Sie zuerst :parent ein.',
    'config_locked' => 'config/atrium.php konnte nicht bearbeitet werden.',
    'required_title' => 'Eingebunden, nicht installiert',
    'required_hint' => 'Pakete aus composer.json, die noch nicht installiert sind. Installieren mit:',
    'catalogue_title' => 'Katalog',
    'catalogue_hint' => 'Auf Packagist veröffentlichte Atrium-Module. Zum Hinzufügen im Projektordner ausführen:',
    'catalogue_loading' => 'Katalog wird geladen…',
    'catalogue_unavailable' => 'Packagist hat nicht geantwortet. Versuchen Sie es später erneut.',
    'catalogue_empty' => 'Keine Module auf Packagist gefunden.',
    'status_installed' => 'Installiert',
    'status_disabled' => 'Ausgeschaltet',
    'status_required' => 'Eingebunden',
    'downloads' => ':count Downloads',
];

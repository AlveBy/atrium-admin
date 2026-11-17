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
    'title' => 'Moduli',
    'index_hint' => 'I pacchetti di moduli installati su questo sito. Un modulo con impostazioni ha un pulsante per aprirle.',
    'empty' => 'Nessun pacchetto di moduli installato.',
    'module' => 'Modulo',
    'package' => 'Pacchetto',
    'settings' => 'Impostazioni',
    'settings_title' => ':module: impostazioni',
    'per_site' => 'Queste sono le impostazioni del sito «:site». Scegli un altro sito nell’intestazione per impostare le sue.',
    'saved' => 'Impostazioni salvate.',
    'enable' => 'Attiva',
    'disable' => 'Disattiva',
    'enabled' => ':module è attivato.',
    'disabled' => ':module è disattivato. I suoi dati e le impostazioni restano.',
    'parent_off' => 'Attiva prima :parent.',
    'config_locked' => 'Impossibile modificare config/atrium.php.',
    'required_title' => 'Richiesti, non installati',
    'required_hint' => 'Pacchetti di composer.json non ancora installati. Per installarne uno:',
    'catalogue_title' => 'Catalogo',
    'catalogue_hint' => 'Moduli Atrium pubblicati su Packagist. Per aggiungerne uno, esegui nella cartella del progetto:',
    'catalogue_loading' => 'Caricamento del catalogo…',
    'catalogue_unavailable' => 'Packagist non ha risposto. Riprova più tardi.',
    'catalogue_empty' => 'Nessun modulo trovato su Packagist.',
    'status_installed' => 'Installato',
    'status_disabled' => 'Disattivato',
    'status_required' => 'Richiesto',
    'downloads' => ':count download',
];

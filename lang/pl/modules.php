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
    'title' => 'Moduły',
    'index_hint' => 'Pakiety modułów zainstalowane w tej witrynie. Moduł z ustawieniami ma do nich przycisk.',
    'empty' => 'Nie zainstalowano żadnych pakietów modułów.',
    'module' => 'Moduł',
    'package' => 'Pakiet',
    'settings' => 'Ustawienia',
    'settings_title' => ':module: ustawienia',
    'per_site' => 'To są ustawienia witryny „:site”. Wybierz inną witrynę w nagłówku, aby ustawić jej własne.',
    'saved' => 'Ustawienia zapisane.',
    'enable' => 'Włącz',
    'disable' => 'Wyłącz',
    'enabled' => 'Moduł :module jest włączony.',
    'disabled' => 'Moduł :module jest wyłączony. Jego dane i ustawienia zostają.',
    'parent_off' => 'Najpierw włącz :parent.',
    'config_locked' => 'Nie udało się zmienić config/atrium.php.',
    'required_title' => 'Wymagane, niezainstalowane',
    'required_hint' => 'Pakiety z composer.json, które nie są jeszcze zainstalowane. Instalacja:',
    'catalogue_title' => 'Katalog',
    'catalogue_hint' => 'Moduły Atrium opublikowane w Packagist. Aby dodać moduł, uruchom w folderze projektu:',
    'catalogue_loading' => 'Wczytywanie katalogu…',
    'catalogue_unavailable' => 'Packagist nie odpowiedział. Spróbuj później.',
    'catalogue_empty' => 'Nie znaleziono modułów w Packagist.',
    'status_installed' => 'Zainstalowany',
    'status_disabled' => 'Wyłączony',
    'status_required' => 'Wymagany',
    'downloads' => 'Pobrania: :count',
];

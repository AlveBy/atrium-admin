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
    'title' => 'Модулі',
    'index_hint' => 'Пакети модулів, встановлені на сайті. Модуль із налаштуваннями має кнопку до них.',
    'empty' => 'Пакети модулів не встановлено.',
    'module' => 'Модуль',
    'package' => 'Пакет',
    'settings' => 'Налаштування',
    'settings_title' => ':module: налаштування',
    'per_site' => 'Це налаштування сайту «:site». Щоб налаштувати інший сайт, виберіть його в шапці.',
    'saved' => 'Налаштування збережено.',
    'enable' => 'Увімкнути',
    'disable' => 'Вимкнути',
    'enabled' => 'Модуль «:module» увімкнено.',
    'disabled' => 'Модуль «:module» вимкнено. Його дані та налаштування збережено.',
    'parent_off' => 'Спочатку увімкніть «:parent».',
    'config_locked' => 'Не вдалося змінити config/atrium.php.',
    'required_title' => 'Підключені, але не встановлені',
    'required_hint' => 'Пакети з composer.json, які ще не встановлені. Встановити:',
    'catalogue_title' => 'Каталог',
    'catalogue_hint' => 'Модулі Atrium, опубліковані на Packagist. Щоб додати модуль, виконайте в теці проєкту:',
    'catalogue_loading' => 'Завантажуємо каталог…',
    'catalogue_unavailable' => 'Packagist не відповів. Спробуйте пізніше.',
    'catalogue_empty' => 'На Packagist модулів не знайдено.',
    'status_installed' => 'Встановлено',
    'status_disabled' => 'Вимкнено',
    'status_required' => 'Підключено',
    'downloads' => 'Завантажень: :count',
];

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
    'title' => 'Модули',
    'index_hint' => 'Пакеты модулей, установленные на сайте. У модуля с настройками есть кнопка к ним.',
    'empty' => 'Пакеты модулей не установлены.',
    'module' => 'Модуль',
    'package' => 'Пакет',
    'settings' => 'Настройки',
    'settings_title' => ':module: настройки',
    'per_site' => 'Это настройки сайта «:site». Чтобы настроить другой сайт, выберите его в шапке.',
    'saved' => 'Настройки сохранены.',
    'enable' => 'Включить',
    'disable' => 'Выключить',
    'enabled' => 'Модуль «:module» включён.',
    'disabled' => 'Модуль «:module» выключен. Его данные и настройки сохранены.',
    'parent_off' => 'Сначала включите «:parent».',
    'config_locked' => 'Не удалось изменить config/atrium.php.',
    'required_title' => 'Подключены, но не установлены',
    'required_hint' => 'Пакеты из composer.json, которые ещё не установлены. Установить:',
    'catalogue_title' => 'Каталог',
    'catalogue_hint' => 'Модули Atrium, опубликованные на Packagist. Чтобы добавить модуль, выполните в папке проекта:',
    'catalogue_loading' => 'Загружаем каталог…',
    'catalogue_unavailable' => 'Packagist не ответил. Попробуйте позже.',
    'catalogue_empty' => 'На Packagist модулей не найдено.',
    'status_installed' => 'Установлен',
    'status_disabled' => 'Выключен',
    'status_required' => 'Подключён',
    'downloads' => 'Скачиваний: :count',
];

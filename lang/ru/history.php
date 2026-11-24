<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Журнал изменений',
    'subtitle' => 'Кто, когда и что изменил во всех модулях, которые вам доступны.',
    'record_title' => 'История: :title',
    'button' => 'История',
    'empty' => 'Пока ничего не записано.',
    'filters' => [
        'module' => 'Модуль',
        'admin' => 'Администратор',
        'from' => 'С',
        'to' => 'По',
        'all' => 'Все',
        'apply' => 'Применить',
        'reset' => 'Сбросить',
    ],
    'events' => [
        'created' => 'Создано',
        'updated' => 'Изменено',
        'deleted' => 'Удалено',
        'restored' => 'Восстановлено',
    ],
    'sources' => [
        'admin' => 'в панели',
        'api' => 'через API',
        'import' => 'импортом',
        'console' => 'командой',
        'schedule' => 'по расписанию',
    ],
    'system' => 'Система',
    'field' => 'Поле',
    'old' => 'Было',
    'new' => 'Стало',
    'secret' => 'изменено (значение не хранится)',
    'empty_value' => 'пусто',
    'yes' => 'да',
    'no' => 'нет',
    'back' => 'К записи',
];

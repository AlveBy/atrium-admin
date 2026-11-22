<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Выгрузка списка модуля в файлы CSV и Excel.
 */
return [
    'title' => 'Выгрузка',
    'csv' => 'CSV',
    'xlsx' => 'Excel (.xlsx)',

    'console' => [
        'module' => 'Модуля «:module» с выгрузкой нет. Есть: :modules.',
        'format' => 'Формат должен быть одним из: :formats.',
        'done' => 'Записей выгружено: :count, в :file.',
    ],
];

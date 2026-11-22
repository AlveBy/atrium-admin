<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Export of a module's list to CSV and Excel files.
 */
return [
    'title' => 'エクスポート',
    'csv' => 'CSV',
    'xlsx' => 'Excel (.xlsx)',

    'console' => [
        'module' => 'モジュール「:module」はエクスポートできません。エクスポートできるモジュール：:modules。',
        'format' => '形式は次のいずれかにしてください：:formats。',
        'done' => '書き出したレコード：:count 件、:file へ。',
    ],
];

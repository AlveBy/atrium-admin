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
    'title' => 'Eksport',
    'csv' => 'CSV',
    'xlsx' => 'Excel (.xlsx)',

    'console' => [
        'module' => 'Moduł „:module” nie obsługuje eksportu. Te, które go obsługują: :modules.',
        'format' => 'Format musi być jednym z: :formats.',
        'done' => 'Zapisano rekordów: :count, do :file.',
    ],
];

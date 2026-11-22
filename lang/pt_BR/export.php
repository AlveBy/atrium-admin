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
    'title' => 'Exportação',
    'csv' => 'CSV',
    'xlsx' => 'Excel (.xlsx)',

    'console' => [
        'module' => 'O módulo “:module” não pode ser exportado. Os que podem: :modules.',
        'format' => 'O formato deve ser um destes: :formats.',
        'done' => 'Registros gravados: :count, em :file.',
    ],
];

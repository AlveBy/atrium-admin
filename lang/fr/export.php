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
    'title' => 'Export',
    'csv' => 'CSV',
    'xlsx' => 'Excel (.xlsx)',

    'console' => [
        'module' => 'Aucun module « :module » ne peut être exporté. Ceux qui le peuvent : :modules.',
        'format' => 'Le format doit être l’un de ceux-ci : :formats.',
        'done' => 'Enregistrements écrits : :count, dans :file.',
    ],
];

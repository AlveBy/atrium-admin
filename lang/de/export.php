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
        'module' => 'Das Modul „:module“ lässt sich nicht exportieren. Diese lassen sich exportieren: :modules.',
        'format' => 'Das Format muss eines von diesen sein: :formats.',
        'done' => 'Datensätze geschrieben: :count, nach :file.',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Buttons of a module: on a record, and over the records selected in a list.
 */
return [
    'confirm' => 'Are you sure?',
    'confirm_title' => 'Confirm the action',
    'run' => 'Continue',
    'done' => 'Done.',
    'failed' => 'The action failed.',
    'queued' => 'Started for :count record. It runs in the background.|Started for :count records. They are processed in the background.',
    'nothing_selected' => 'None of the selected records was found.',
    'new_tab' => '(opens in a new tab)',
    'success' => 'Success',
    'error' => 'Error',

    'bulk' => [
        'region' => 'Actions on the selected records',
        'selected' => ':count selected|:count selected',
        'clear' => 'Clear selection',
        'select_page' => 'Select all on this page',
        'select_row' => 'Select :title',
    ],

    'delete' => [
        'label' => 'Delete',
        'confirm' => 'Delete the selected records? This cannot be undone.',
        'done' => ':count record deleted.|:count records deleted.',
        'kept' => ':count record could not be deleted.|:count records could not be deleted.',
    ],
];

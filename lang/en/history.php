<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Activity log',
    'subtitle' => 'Who changed what and when, in every module you can open.',
    'record_title' => 'History: :title',
    'button' => 'History',
    'empty' => 'Nothing has been recorded yet.',
    'filters' => [
        'module' => 'Module',
        'admin' => 'Administrator',
        'from' => 'From',
        'to' => 'To',
        'all' => 'All',
        'apply' => 'Apply',
        'reset' => 'Reset',
    ],
    'events' => [
        'created' => 'Created',
        'updated' => 'Changed',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
    ],
    'sources' => [
        'admin' => 'in the panel',
        'api' => 'via API',
        'import' => 'by import',
        'console' => 'by a command',
        'schedule' => 'by the scheduler',
    ],
    'system' => 'System',
    'field' => 'Field',
    'old' => 'Was',
    'new' => 'Now',
    'secret' => 'changed (the value is not kept)',
    'empty_value' => 'empty',
    'yes' => 'yes',
    'no' => 'no',
    'back' => 'Back to the record',
    'versions' => [
        'title' => 'Versions',
        'hint' => 'What the form held after each save. Compare two of them, or put an older one back.',
        'baseline' => 'Before the history began',
        'current' => 'now',
        'from' => 'Compare',
        'to' => 'with',
        'compare' => 'Compare',
        'same' => 'The two versions are the same.',
        'restore' => 'Restore',
        'confirm_title' => 'Restore this version?',
        'confirm' => 'The record gets the values of this version. What it holds now stays here as a version of its own.',
        'restored' => 'The version is restored.',
        'failed' => 'The version cannot be restored: :errors',
    ],
];

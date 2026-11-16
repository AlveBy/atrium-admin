<?php

declare(strict_types=1);

return [
    'create' => 'Add language',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'active' => 'Active',
        'is_default' => 'Default',
        'sort' => 'Sort order',
    ],
    'hints' => [
        'name' => 'As visitors see it in the language switch: English, Deutsch, Español.',
        'code' => 'Two or three lowercase letters, optionally with a region: en, de, pt-br. It becomes the address prefix (/de/…) and cannot be changed later.',
        'code_fixed' => 'Translations are stored under this code and addresses start with it, so it cannot be changed.',
        'is_default' => 'Its pages have no language prefix, and its text is shown where a translation is missing.',
    ],
    'switch' => 'Content language',
];

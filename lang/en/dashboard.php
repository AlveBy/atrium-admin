<?php

declare(strict_types=1);

/*
 * Dashboard page and its gadgets.
 */
return [
    'title' => 'Dashboard',

    'site_speed' => [
        'title' => 'Site speed',
        'no_data' => 'no data',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Very fast',
            'fast' => 'Fast',
            'not_fast' => 'Not fast',
            'slow' => 'Slow',
            'very_slow' => 'Very slow',
        ],
        'samples_note' => 'median of :samples measurements over :days days',
        'no_samples' => 'no measurements yet — the numbers will appear after the first visits to the site',
    ],

    'performance' => [
        'title' => 'Performance monitor',
        'details' => 'Details',
        'collapse' => 'Collapse',
        'caption' => 'Current score',
        'unit' => 'pages per second',
        'pending' => 'The score will appear after the first visits to the site',
        'note' => 'average generation time :seconds s · :pages pages over :days days',
        'slowest_title' => 'Heaviest pages',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Recent items',
        'empty' => 'No items yet.',
        'all' => 'All items',
    ],
];

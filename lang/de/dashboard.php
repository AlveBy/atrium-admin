<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Dashboard',

    'site_speed' => [
        'title' => 'Website-Geschwindigkeit',
        'no_data' => 'keine Daten',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Sehr schnell',
            'fast' => 'Schnell',
            'not_fast' => 'Nicht schnell',
            'slow' => 'Langsam',
            'very_slow' => 'Sehr langsam',
        ],
        'samples_note' => 'Median aus :samples Messungen in :days Tagen',
        'no_samples' => 'noch keine Messungen – die Werte erscheinen nach den ersten Besuchen der Website',
    ],

    'performance' => [
        'title' => 'Leistungsmonitor',
        'details' => 'Details',
        'collapse' => 'Einklappen',
        'caption' => 'Aktueller Wert',
        'unit' => 'Seiten pro Sekunde',
        'pending' => 'Der Wert erscheint nach den ersten Besuchen der Website',
        'note' => 'durchschnittliche Generierungszeit :seconds s · :pages Seiten in :days Tagen',
        'slowest_title' => 'Aufwendigste Seiten',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Neueste Einträge',
        'empty' => 'Noch keine Einträge.',
        'all' => 'Alle Einträge',
    ],
];

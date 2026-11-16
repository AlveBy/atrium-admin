<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Dashboard',

    'site_speed' => [
        'title' => 'Velocità del sito',
        'no_data' => 'nessun dato',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Molto veloce',
            'fast' => 'Veloce',
            'not_fast' => 'Poco veloce',
            'slow' => 'Lento',
            'very_slow' => 'Molto lento',
        ],
        'samples_note' => 'mediana di :samples misurazioni in :days giorni',
        'no_samples' => 'ancora nessuna misurazione — i valori compariranno dopo le prime visite al sito',
    ],

    'performance' => [
        'title' => 'Monitor delle prestazioni',
        'details' => 'Dettagli',
        'collapse' => 'Comprimi',
        'caption' => 'Punteggio attuale',
        'unit' => 'pagine al secondo',
        'pending' => 'Il punteggio comparirà dopo le prime visite al sito',
        'note' => 'tempo medio di generazione :seconds s · :pages pagine in :days giorni',
        'slowest_title' => 'Pagine più pesanti',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Elementi recenti',
        'empty' => 'Ancora nessun elemento.',
        'all' => 'Tutti gli elementi',
    ],
];

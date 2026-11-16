<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Escritorio',

    'site_speed' => [
        'title' => 'Velocidad del sitio',
        'no_data' => 'sin datos',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Muy rápido',
            'fast' => 'Rápido',
            'not_fast' => 'Poco rápido',
            'slow' => 'Lento',
            'very_slow' => 'Muy lento',
        ],
        'samples_note' => 'mediana de :samples mediciones en :days días',
        'no_samples' => 'todavía no hay mediciones; las cifras aparecerán tras las primeras visitas al sitio',
    ],

    'performance' => [
        'title' => 'Monitor de rendimiento',
        'details' => 'Detalles',
        'collapse' => 'Contraer',
        'caption' => 'Puntuación actual',
        'unit' => 'páginas por segundo',
        'pending' => 'La puntuación aparecerá tras las primeras visitas al sitio',
        'note' => 'tiempo medio de generación :seconds s · :pages páginas en :days días',
        'slowest_title' => 'Páginas más pesadas',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Elementos recientes',
        'empty' => 'Todavía no hay elementos.',
        'all' => 'Todos los elementos',
    ],
];

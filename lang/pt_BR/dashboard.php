<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Painel',

    'site_speed' => [
        'title' => 'Velocidade do site',
        'no_data' => 'sem dados',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Muito rápido',
            'fast' => 'Rápido',
            'not_fast' => 'Pouco rápido',
            'slow' => 'Lento',
            'very_slow' => 'Muito lento',
        ],
        'samples_note' => 'mediana de :samples medições em :days dias',
        'no_samples' => 'ainda não há medições — os números aparecerão após as primeiras visitas ao site',
    ],

    'performance' => [
        'title' => 'Monitor de desempenho',
        'details' => 'Detalhes',
        'collapse' => 'Recolher',
        'caption' => 'Pontuação atual',
        'unit' => 'páginas por segundo',
        'pending' => 'A pontuação aparecerá após as primeiras visitas ao site',
        'note' => 'tempo médio de geração :seconds s · :pages páginas em :days dias',
        'slowest_title' => 'Páginas mais pesadas',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Itens recentes',
        'empty' => 'Nenhum item ainda.',
        'all' => 'Todos os itens',
    ],
];

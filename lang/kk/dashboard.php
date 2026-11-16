<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Басқару тақтасы',

    'site_speed' => [
        'title' => 'Сайт жылдамдығы',
        'no_data' => 'дерек жоқ',
        'unit' => 'с',
        'bands' => [
            'very_fast' => 'Өте жылдам',
            'fast' => 'Жылдам',
            'not_fast' => 'Онша жылдам емес',
            'slow' => 'Баяу',
            'very_slow' => 'Өте баяу',
        ],
        'samples_note' => ':days күндегі :samples өлшемнің медианасы',
        'no_samples' => 'әзірге өлшемдер жоқ — сандар сайтқа алғашқы кірулерден кейін пайда болады',
    ],

    'performance' => [
        'title' => 'Өнімділік мониторы',
        'details' => 'Толығырақ',
        'collapse' => 'Жию',
        'caption' => 'Ағымдағы баға',
        'unit' => 'секундына бет',
        'pending' => 'Баға сайтқа алғашқы кірулерден кейін пайда болады',
        'note' => 'орташа генерациялау уақыты :seconds с · :days күнде :pages бет',
        'slowest_title' => 'Ең ауыр беттер',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Соңғы элементтер',
        'empty' => 'Әзірге элементтер жоқ.',
        'all' => 'Барлық элементтер',
    ],
];

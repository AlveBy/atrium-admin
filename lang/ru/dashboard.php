<?php

declare(strict_types=1);

/*
 * Dashboard page and its gadgets.
 */
return [
    'title' => 'Рабочий стол',

    'site_speed' => [
        'title' => 'Скорость сайта',
        'no_data' => 'нет данных',
        'unit' => 'Сек.',
        'bands' => [
            'very_fast' => 'Очень быстро',
            'fast' => 'Быстро',
            'not_fast' => 'Не быстро',
            'slow' => 'Медленно',
            'very_slow' => 'Очень медленно',
        ],
        'samples_note' => 'медиана по :samples замерам за :days дней',
        'no_samples' => 'замеров пока нет — числа появятся после первых заходов на сайт',
    ],

    'performance' => [
        'title' => 'Монитор производительности',
        'details' => 'Подробнее',
        'collapse' => 'Свернуть',
        'caption' => 'Текущая оценка',
        'unit' => 'страниц в секунду',
        'pending' => 'Оценка появится после первых заходов на сайт',
        'note' => 'среднее время генерации :seconds сек · :pages страниц за :days дней',
        'slowest_title' => 'Самые тяжёлые страницы',
        'slowest_row' => ':ms мс · :hits',
    ],

    'recent' => [
        'title' => 'Последние записи',
        'empty' => 'Записей пока нет.',
        'all' => 'Все записи',
    ],
];

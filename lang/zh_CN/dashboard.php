<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => '仪表板',

    'site_speed' => [
        'title' => '网站速度',
        'no_data' => '无数据',
        'unit' => '秒',
        'bands' => [
            'very_fast' => '非常快',
            'fast' => '快',
            'not_fast' => '一般',
            'slow' => '慢',
            'very_slow' => '非常慢',
        ],
        'samples_note' => ':days 天内 :samples 次测量的中位数',
        'no_samples' => '暂无测量数据 — 网站有访问后将显示数值',
    ],

    'performance' => [
        'title' => '性能监视器',
        'details' => '详细信息',
        'collapse' => '收起',
        'caption' => '当前得分',
        'unit' => '页/秒',
        'pending' => '网站有访问后将显示得分',
        'note' => '平均生成时间 :seconds 秒 · :days 天内 :pages 个页面',
        'slowest_title' => '最慢的页面',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => '最近的条目',
        'empty' => '暂无条目。',
        'all' => '所有条目',
    ],
];

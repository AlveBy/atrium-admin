<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'ダッシュボード',

    'site_speed' => [
        'title' => 'サイトの速度',
        'no_data' => 'データなし',
        'unit' => '秒',
        'bands' => [
            'very_fast' => '非常に速い',
            'fast' => '速い',
            'not_fast' => 'やや遅い',
            'slow' => '遅い',
            'very_slow' => '非常に遅い',
        ],
        'samples_note' => ':days 日間の :samples 回の測定の中央値',
        'no_samples' => 'まだ測定データがありません — サイトへの最初のアクセス後に数値が表示されます',
    ],

    'performance' => [
        'title' => 'パフォーマンスモニター',
        'details' => '詳細',
        'collapse' => '折りたたむ',
        'caption' => '現在のスコア',
        'unit' => 'ページ/秒',
        'pending' => 'サイトへの最初のアクセス後にスコアが表示されます',
        'note' => '平均生成時間 :seconds 秒 · :days 日間で :pages ページ',
        'slowest_title' => '最も重いページ',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => '最近の項目',
        'empty' => '項目はまだありません。',
        'all' => 'すべての項目',
    ],
];

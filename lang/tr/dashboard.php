<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Pano',

    'site_speed' => [
        'title' => 'Site hızı',
        'no_data' => 'veri yok',
        'unit' => 'sn',
        'bands' => [
            'very_fast' => 'Çok hızlı',
            'fast' => 'Hızlı',
            'not_fast' => 'Pek hızlı değil',
            'slow' => 'Yavaş',
            'very_slow' => 'Çok yavaş',
        ],
        'samples_note' => ':days günde :samples ölçümün medyanı',
        'no_samples' => 'henüz ölçüm yok — rakamlar siteye yapılan ilk ziyaretlerden sonra görünecek',
    ],

    'performance' => [
        'title' => 'Performans izleyici',
        'details' => 'Ayrıntılar',
        'collapse' => 'Daralt',
        'caption' => 'Güncel puan',
        'unit' => 'sayfa/saniye',
        'pending' => 'Puan, siteye yapılan ilk ziyaretlerden sonra görünecek',
        'note' => 'ortalama oluşturma süresi :seconds sn · :days günde :pages sayfa',
        'slowest_title' => 'En ağır sayfalar',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Son öğeler',
        'empty' => 'Henüz öğe yok.',
        'all' => 'Tüm öğeler',
    ],
];

<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Dil ekle',
    'fields' => [
        'name' => 'Ad',
        'code' => 'Kod',
        'active' => 'Etkin',
        'is_default' => 'Varsayılan',
        'sort' => 'Sıralama',
    ],
    'hints' => [
        'name' => 'Ziyaretçilerin dil seçicide göreceği ad: English, Deutsch, Español.',
        'code' => 'İki veya üç küçük harf, isteğe bağlı olarak bölgeyle birlikte: en, de, pt-br. URL\'nin başı olur (/de/…) ve daha sonra değiştirilemez.',
        'code_fixed' => 'Çeviriler bu kodla saklanır ve URL\'ler bu kodla başlar, bu yüzden değiştirilemez.',
        'is_default' => 'Sayfalarının URL\'sinde dil öneki olmaz; çevirisi olmayan yerlerde bu dilin metni gösterilir.',
    ],
    'switch' => 'İçerik dili',
];

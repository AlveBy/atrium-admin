<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Site ekle',
    'add_domain' => 'Alan adı ekle',
    'fields' => [
        'name' => 'Ad',
        'code' => 'Kod',
        'domains' => 'Alan adları',
        'languages' => 'Diller',
        'default_language' => 'Varsayılan dil',
        'active' => 'Etkin',
        'is_default' => 'Varsayılan site',
        'sort' => 'Sıralama',
    ],
    'hints' => [
        'code' => 'Küçük Latin harfleri, rakamlar, tireler: main, shop, de. Daha sonra değiştirilemez.',
        'domains' => 'Site bu alan adlarında açılır: example.com, www.example.com. İlki ana alan adıdır.',
        'languages' => 'Bu sitenin içerik dilleri. Hiçbiri seçilmezse — tüm etkin diller.',
        'default_language' => 'Bu dilin sayfalarının adresinde dil öneki olmaz. Boşsa — tüm panelin varsayılan dili.',
        'is_default' => 'Başka bir sitenin sahiplenmediği her alan adında ve localhost\'ta açılır.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” bir alan adı değil.',
        'domain_taken' => ':domain alan adı “:site” sitesi tarafından zaten kullanılıyor.',
    ],
];

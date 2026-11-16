<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Sayt qoʻshish',
    'add_domain' => 'Domen qoʻshish',
    'fields' => [
        'name' => 'Nomi',
        'code' => 'Kod',
        'domains' => 'Domenlar',
        'languages' => 'Tillar',
        'default_language' => 'Standart til',
        'active' => 'Faol',
        'is_default' => 'Asosiy sayt',
        'sort' => 'Saralash',
    ],
    'hints' => [
        'code' => 'Kichik lotin harflari, raqamlar, defislar: main, shop, de. Keyin oʻzgartirib boʻlmaydi.',
        'domains' => 'Sayt shu domenlarda ochiladi: example.com, www.example.com. Birinchisi — asosiysi.',
        'languages' => 'Ushbu saytning kontent tillari. Hech narsa tanlanmasa — barcha faol tillar.',
        'default_language' => 'Bu til sahifalari manzilda til prefiksisiz ochiladi. Boʻsh qoldirilsa — butun panelning standart tili.',
        'is_default' => 'Boshqa sayt egallamagan har qanday domenda va localhostda ochiladi.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” domen nomi emas.',
        'domain_taken' => ':domain domenidan “:site” sayti allaqachon foydalanmoqda.',
    ],
];

<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Dodaj witrynę',
    'add_domain' => 'Dodaj domenę',
    'fields' => [
        'name' => 'Nazwa',
        'code' => 'Kod',
        'domains' => 'Domeny',
        'languages' => 'Języki',
        'default_language' => 'Język domyślny',
        'active' => 'Aktywna',
        'is_default' => 'Domyślna witryna',
        'sort' => 'Kolejność',
    ],
    'hints' => [
        'code' => 'Małe litery łacińskie, cyfry, myślniki: main, shop, de. Później nie można tego zmienić.',
        'domains' => 'Witryna otwiera się pod tymi domenami: example.com, www.example.com. Pierwsza jest główną.',
        'languages' => 'Języki treści tej witryny. Nic nie wybrano — wszystkie aktywne języki.',
        'default_language' => 'Strony tego języka nie mają prefiksu języka w adresie. Puste — domyślny język całego panelu.',
        'is_default' => 'Otwiera się pod każdą domeną, której nie zgłasza żadna inna witryna, oraz pod localhost.',
    ],
    'errors' => [
        'domain_invalid' => '„:domain” nie jest nazwą domeny.',
        'domain_taken' => 'Domena :domain jest już używana przez witrynę „:site”.',
    ],
];

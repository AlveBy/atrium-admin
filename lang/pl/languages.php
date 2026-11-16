<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Dodaj język',
    'fields' => [
        'name' => 'Nazwa',
        'code' => 'Kod',
        'active' => 'Aktywny',
        'is_default' => 'Domyślny',
        'sort' => 'Kolejność',
    ],
    'hints' => [
        'name' => 'Tak zobaczą go odwiedzający w przełączniku języków: English, Deutsch, Español.',
        'code' => 'Dwie lub trzy małe litery, opcjonalnie z regionem: en, de, pt-br. Stanie się początkiem adresu (/de/…) i później nie da się go zmienić.',
        'code_fixed' => 'Pod tym kodem zapisane są tłumaczenia i od niego zaczynają się adresy, dlatego nie można go zmienić.',
        'is_default' => 'Jego strony nie mają prefiksu języka w adresie, a jego tekst jest wyświetlany tam, gdzie brakuje tłumaczenia.',
    ],
    'switch' => 'Język treści',
];

<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Aggiungi sito',
    'add_domain' => 'Aggiungi dominio',
    'fields' => [
        'name' => 'Nome',
        'code' => 'Codice',
        'domains' => 'Domini',
        'languages' => 'Lingue',
        'default_language' => 'Lingua predefinita',
        'active' => 'Attivo',
        'is_default' => 'Sito predefinito',
        'sort' => 'Ordinamento',
    ],
    'hints' => [
        'code' => 'Lettere latine minuscole, cifre, trattini: main, shop, de. In seguito non si può più modificare.',
        'domains' => 'Il sito si apre su questi domini: example.com, www.example.com. Il primo è quello principale.',
        'languages' => 'Le lingue dei contenuti di questo sito. Nessuna selezionata: tutte le lingue attive.',
        'default_language' => 'Le sue pagine non hanno il prefisso della lingua nell\'URL. Vuoto: la lingua predefinita dell\'intero pannello.',
        'is_default' => 'Si apre su qualsiasi dominio che nessun altro sito rivendica e su localhost.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» non è un nome di dominio.',
        'domain_taken' => 'Il dominio :domain è già usato dal sito «:site».',
    ],
];

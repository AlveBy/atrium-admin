<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Aggiungi lingua',
    'fields' => [
        'name' => 'Nome',
        'code' => 'Codice',
        'active' => 'Attiva',
        'is_default' => 'Predefinita',
        'sort' => 'Ordinamento',
    ],
    'hints' => [
        'name' => 'Come lo vedranno i visitatori nel selettore della lingua: English, Deutsch, Español.',
        'code' => 'Due o tre lettere minuscole, eventualmente con la regione: en, de, pt-br. Diventa l\'inizio dell\'URL (/de/…) e in seguito non si può più modificare.',
        'code_fixed' => 'Le traduzioni sono salvate con questo codice e gli URL iniziano con esso, perciò non si può modificare.',
        'is_default' => 'Le sue pagine non hanno il prefisso della lingua nell\'URL e il suo testo viene mostrato dove manca una traduzione.',
    ],
    'switch' => 'Lingua dei contenuti',
];

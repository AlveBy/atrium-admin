<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Aggiungi sito',
    'tabs' => [
        'site' => 'Sito',
        'codes' => 'Contatori e codici',
    ],
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
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Contatore Yandex Metrica',
        'yandex_webvisor' => 'Replay delle sessioni (Webvisor)',
        'counters_skip_admins' => 'Non contare gli amministratori',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Codice in <head>',
        'body_start_code' => 'Codice all\'inizio di <body>',
        'body_end_code' => 'Codice prima di </body>',
    ],
    'hints' => [
        'code' => 'Lettere latine minuscole, cifre, trattini: main, shop, de. In seguito non si può più modificare.',
        'domains' => 'Il sito si apre su questi domini: example.com, www.example.com. Il primo è quello principale.',
        'languages' => 'Le lingue dei contenuti di questo sito. Nessuna selezionata: tutte le lingue attive.',
        'default_language' => 'Le sue pagine non hanno il prefisso della lingua nell\'URL. Vuoto: la lingua predefinita dell\'intero pannello.',
        'is_default' => 'Si apre su qualsiasi dominio che nessun altro sito rivendica e su localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX per Google Analytics 4 o GTM-XXXXXXX per Tag Manager. Solo l\'ID: il codice del contatore viene aggiunto da solo a ogni pagina.',
        'yandex_metrika_id' => 'Solo il numero del contatore dalle impostazioni di Metrica: il codice viene aggiunto da solo a ogni pagina.',
        'counters_skip_admins' => 'Gli amministratori che hanno effettuato l\'accesso ricevono le pagine senza Google Tag e Yandex Metrica, così le loro visite non falsano le statistiche. I tag di verifica e il codice qui sotto sono sempre presenti.',
        'verification' => 'Il codice del meta tag :name. Puoi incollare l\'intero tag.',
        'snippet' => 'Aggiunto così com\'è a ogni pagina di questo sito: un widget di chat, un pixel, qualsiasi altro contatore.',
        'robots_txt' => 'Vuoto: i robot possono indicizzare tutto. Se manca, viene aggiunta una riga «Sitemap:» con il sitemap.xml di questo sito.',
        'robots_not_production' => 'Questo server non è di produzione (APP_ENV=:env), quindi robots.txt vieta l\'indicizzazione qualunque cosa sia scritta qui.',
        'robots_file_exists' => 'Esiste il file public/robots.txt: il web server restituisce quello al posto di questo testo. Elimina il file.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» non è un nome di dominio.',
        'domain_taken' => 'Il dominio :domain è già usato dal sito «:site».',
    ],
];

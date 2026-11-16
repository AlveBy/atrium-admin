<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Website hinzufügen',
    'tabs' => [
        'site' => 'Website',
        'codes' => 'Zähler und Codes',
    ],
    'add_domain' => 'Domain hinzufügen',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'domains' => 'Domains',
        'folder' => 'Ordner',
        'languages' => 'Sprachen',
        'default_language' => 'Standardsprache',
        'active' => 'Aktiv',
        'is_default' => 'Standard-Website',
        'sort' => 'Sortierung',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex-Metrica-Zähler',
        'yandex_webvisor' => 'Sitzungswiedergabe (Webvisor)',
        'counters_skip_admins' => 'Administratoren nicht zählen',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Code in <head>',
        'body_start_code' => 'Code am Anfang von <body>',
        'body_end_code' => 'Code vor </body>',
    ],
    'hints' => [
        'code' => 'Kleinbuchstaben, Ziffern und Bindestriche: main, shop, de. Lässt sich später nicht mehr ändern.',
        'domains' => 'Die Website öffnet sich unter diesen Domains: example.com, www.example.com. Die erste ist die Hauptdomain.',
        'folder' => 'Für mehrere Websites auf einer Domain: site2 öffnet diese Website unter example.com/site2/. Leer – die Website liegt im Stamm ihrer Domains. Eine Website ohne Domains liegt in diesem Ordner auf jeder Domain.',
        'languages' => 'Die Inhaltssprachen dieser Website. Keine ausgewählt – alle aktiven Sprachen.',
        'default_language' => 'Ihre Seiten haben kein Sprachpräfix in der URL. Leer – die Standardsprache des gesamten Panels.',
        'is_default' => 'Wird unter jeder Domain geöffnet, die keine andere Website beansprucht, sowie unter localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX für Google Analytics 4 oder GTM-XXXXXXX für Tag Manager. Nur die ID: Der Zählercode wird automatisch auf jeder Seite eingefügt.',
        'yandex_metrika_id' => 'Nur die Zählernummer aus den Metrica-Einstellungen: Der Code wird automatisch auf jeder Seite eingefügt.',
        'counters_skip_admins' => 'Angemeldete Administratoren erhalten Seiten ohne Google Tag und Yandex Metrica, damit ihre eigenen Besuche die Statistik nicht verfälschen. Bestätigungs-Tags und der Code unten sind immer vorhanden.',
        'verification' => 'Der Code aus dem Meta-Tag :name. Sie können das ganze Tag einfügen.',
        'snippet' => 'Wird unverändert auf jeder Seite dieser Website eingefügt: Chat-Widget, Pixel, jeder andere Zähler.',
        'robots_txt' => 'Leer – Robots dürfen alles indexieren. Eine Zeile „Sitemap:“ mit der sitemap.xml dieser Website wird ergänzt, falls keine vorhanden ist.',
        'robots_not_production' => 'Dieser Server ist nicht produktiv (APP_ENV=:env), daher verbietet robots.txt die Indexierung, unabhängig vom hier Eingetragenen.',
        'robots_file_exists' => 'Die Datei public/robots.txt existiert: Der Webserver liefert sie anstelle dieses Textes aus. Löschen Sie die Datei.',
    ],
    'errors' => [
        'domain_invalid' => '„:domain“ ist kein Domainname.',
        'domain_taken' => 'Die Domain :domain wird bereits von der Website „:site“ verwendet.',
        'folder_invalid' => '„:folder“ ist kein Ordner: lateinische Buchstaben, Ziffern, Binde- und Unterstriche, Teile durch Schrägstriche getrennt.',
        'folder_reserved' => 'Der Ordner :folder ist eine Adresse des Panels selbst.',
        'folder_default' => 'Die Standard-Website beantwortet jede Adresse, die keine andere Website beansprucht, und liegt deshalb im Stamm. Leeren Sie den Ordner oder machen Sie eine andere Website zum Standard.',
        'folder_taken' => 'Die Adresse :address wird bereits von der Website „:site“ verwendet.',
    ],
];

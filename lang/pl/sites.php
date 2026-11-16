<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Dodaj witrynę',
    'tabs' => [
        'site' => 'Witryna',
        'codes' => 'Liczniki i kody',
    ],
    'add_domain' => 'Dodaj domenę',
    'fields' => [
        'name' => 'Nazwa',
        'code' => 'Kod',
        'domains' => 'Domeny',
        'folder' => 'Folder',
        'languages' => 'Języki',
        'default_language' => 'Język domyślny',
        'active' => 'Aktywna',
        'is_default' => 'Domyślna witryna',
        'sort' => 'Kolejność',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Licznik Yandex Metrica',
        'yandex_webvisor' => 'Odtwarzanie sesji (Webvisor)',
        'counters_skip_admins' => 'Nie licz administratorów',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Kod w <head>',
        'body_start_code' => 'Kod na początku <body>',
        'body_end_code' => 'Kod przed </body>',
    ],
    'hints' => [
        'code' => 'Małe litery łacińskie, cyfry, myślniki: main, shop, de. Później nie można tego zmienić.',
        'domains' => 'Witryna otwiera się pod tymi domenami: example.com, www.example.com. Pierwsza jest główną.',
        'folder' => 'Dla kilku witryn w jednej domenie: site2 otwiera tę witrynę pod adresem example.com/site2/. Puste — witryna jest w katalogu głównym swoich domen. Witryna bez domen jest w tym folderze w każdej domenie.',
        'languages' => 'Języki treści tej witryny. Nic nie wybrano — wszystkie aktywne języki.',
        'default_language' => 'Strony tego języka nie mają prefiksu języka w adresie. Puste — domyślny język całego panelu.',
        'is_default' => 'Otwiera się pod każdą domeną, której nie zgłasza żadna inna witryna, oraz pod localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX dla Google Analytics 4 lub GTM-XXXXXXX dla Tag Manager. Tylko identyfikator: kod licznika sam dodaje się na każdej stronie.',
        'yandex_metrika_id' => 'Tylko numer licznika z ustawień Metrica: kod sam dodaje się na każdej stronie.',
        'counters_skip_admins' => 'Zalogowani administratorzy dostają strony bez Google Tag i Yandex Metrica, aby ich własne wizyty nie psuły statystyk. Tagi weryfikacyjne i kod poniżej są zawsze obecne.',
        'verification' => 'Kod z metatagu :name. Można wkleić cały tag.',
        'snippet' => 'Dodawany bez zmian na każdej stronie tej witryny: widżet czatu, piksel, dowolny inny licznik.',
        'robots_txt' => 'Puste — roboty mogą indeksować wszystko. Wiersz „Sitemap:” z adresem sitemap.xml tej witryny zostanie dodany, jeśli go nie ma.',
        'robots_not_production' => 'Ten serwer nie jest produkcyjny (APP_ENV=:env), więc robots.txt zabrania indeksowania, cokolwiek tu wpisano.',
        'robots_file_exists' => 'Istnieje plik public/robots.txt: serwer WWW zwraca go zamiast tego tekstu. Usuń plik.',
    ],
    'errors' => [
        'domain_invalid' => '„:domain” nie jest nazwą domeny.',
        'domain_taken' => 'Domena :domain jest już używana przez witrynę „:site”.',
        'folder_invalid' => '„:folder” nie jest folderem: litery łacińskie, cyfry, myślniki i podkreślenia, części oddzielone ukośnikami.',
        'folder_reserved' => 'Folder :folder to adres samego panelu.',
        'folder_default' => 'Domyślna witryna odpowiada na każdy adres, którego nie zajęła inna witryna, dlatego jest w katalogu głównym. Wyczyść folder albo ustaw inną witrynę jako domyślną.',
        'folder_taken' => 'Adres :address jest już używany przez witrynę „:site”.',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Дадаць сайт',
    'tabs' => [
        'site' => 'Сайт',
        'codes' => 'Лічыльнікі і коды',
    ],
    'add_domain' => 'Дадаць дамен',
    'fields' => [
        'name' => 'Назва',
        'code' => 'Код',
        'domains' => 'Дамены',
        'folder' => 'Папка',
        'languages' => 'Мовы',
        'default_language' => 'Мова па змаўчанні',
        'active' => 'Актыўны',
        'is_default' => 'Асноўны сайт',
        'sort' => 'Сартаванне',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Лічыльнік Яндэкс Метрыкі',
        'yandex_webvisor' => 'Вэбвізар',
        'counters_skip_admins' => 'Не ўлічваць адміністратараў',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Яндэкс Вэбмайстар',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Код у <head>',
        'body_start_code' => 'Код у пачатку <body>',
        'body_end_code' => 'Код перад </body>',
    ],
    'hints' => [
        'code' => 'Малыя лацінскія літары, лічбы, злучкі: main, shop, de. Потым змяніць нельга.',
        'domains' => 'Сайт адкрываецца на гэтых даменах: example.com, www.example.com. Першы — асноўны.',
        'folder' => 'Для некалькіх сайтаў на адным дамене: site2 адкрые гэты сайт па адрасе example.com/site2/. Пуста — сайт у корані сваіх даменаў. Сайт без даменаў жыве ў гэтай папцы на любым дамене.',
        'languages' => 'Мовы кантэнту гэтага сайта. Нічога не выбрана — усе актыўныя мовы.',
        'default_language' => 'Старонкі гэтай мовы адкрываюцца без моўнага прэфікса ў адрасе. Пуста — мова па змаўчанні для ўсёй панэлі.',
        'is_default' => 'Адкрываецца на любым дамене, які не належыць іншаму сайту, і на localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX для Google Analytics 4 або GTM-XXXXXXX для Tag Manager. Толькі ідэнтыфікатар: код лічыльніка дадаецца на ўсе старонкі сам.',
        'yandex_metrika_id' => 'Толькі нумар лічыльніка з налад Метрыкі: код дадаецца на ўсе старонкі сам.',
        'counters_skip_admins' => 'Аўтарызаваным адміністратарам старонкі аддаюцца без Google Tag і Яндэкс Метрыкі, каб іх наведванні не псавалі статыстыку. Мета-тэгі пацверджання і код ніжэй выводзяцца заўсёды.',
        'verification' => 'Код з мета-тэга :name. Можна ўставіць тэг цалкам.',
        'snippet' => 'Уставляецца як ёсць на ўсе старонкі гэтага сайта: віджэт чата, піксель, любы іншы лічыльнік.',
        'robots_txt' => 'Пуста — робатам можна ўсё. Радок «Sitemap:» са спасылкай на sitemap.xml гэтага сайта дадасца сам, калі яго няма.',
        'robots_not_production' => 'Гэты сервер не баявы (APP_ENV=:env), таму robots.txt забараняе індэксацыю, што б тут ні было напісана.',
        'robots_file_exists' => 'Ёсць файл public/robots.txt: вэб-сервер аддае яго замест гэтага тэксту. Выдаліце файл.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» не з’яўляецца даменным імем.',
        'domain_taken' => 'Дамен :domain ужо выкарыстоўваецца сайтам «:site».',
        'folder_invalid' => '«:folder» — не папка: лацінскія літары, лічбы, злучкі і падкрэсліванні, часткі праз касую рысу.',
        'folder_reserved' => 'Папка :folder — адрас самой панэлі.',
        'folder_default' => 'Сайт па змаўчанні адказвае на ўсе адрасы, якія не заняў іншы сайт, таму жыве ў корані. Ачысціце папку або зрабіце сайтам па змаўчанні іншы сайт.',
        'folder_taken' => 'Адрас :address ужо заняты сайтам «:site».',
    ],
];

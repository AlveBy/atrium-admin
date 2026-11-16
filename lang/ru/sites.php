<?php

declare(strict_types=1);

return [
    'create' => 'Добавить сайт',
    'tabs' => [
        'site' => 'Сайт',
        'codes' => 'Счётчики и коды',
    ],
    'add_domain' => 'Добавить домен',
    'fields' => [
        'name' => 'Название',
        'code' => 'Код',
        'domains' => 'Домены',
        'languages' => 'Языки',
        'default_language' => 'Основной язык',
        'active' => 'Активность',
        'is_default' => 'Сайт по умолчанию',
        'sort' => 'Сортировка',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Счётчик Яндекс Метрики',
        'yandex_webvisor' => 'Вебвизор',
        'counters_skip_admins' => 'Не считать администраторов',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Яндекс Вебмастер',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Код в <head>',
        'body_start_code' => 'Код в начале <body>',
        'body_end_code' => 'Код перед </body>',
    ],
    'hints' => [
        'code' => 'Строчные латинские буквы, цифры, дефис: main, shop, de. Потом не меняется.',
        'domains' => 'На этих доменах открывается сайт: example.com, www.example.com. Первый — главный.',
        'languages' => 'На каких языках содержимое этого сайта. Ничего не выбрано — все активные языки.',
        'default_language' => 'Его страницы открываются без языка в адресе. Пусто — основной язык панели.',
        'is_default' => 'Открывается на любом домене, который не занят другим сайтом, и на localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX для Google Analytics 4 или GTM-XXXXXXX для Tag Manager. Только номер: код счётчика встанет на все страницы сам.',
        'yandex_metrika_id' => 'Только номер счётчика из настроек Метрики: код встанет на все страницы сам.',
        'counters_skip_admins' => 'Вошедшим администраторам страницы отдаются без Google Tag и Яндекс Метрики, чтобы их заходы не портили статистику. Мета-теги подтверждения и код ниже выводятся всегда.',
        'verification' => 'Код из мета-тега :name. Можно вставить тег целиком.',
        'snippet' => 'Вставляется как есть на все страницы этого сайта: виджет чата, пиксель, любой другой счётчик.',
        'robots_txt' => 'Пусто — роботам можно всё. Строка «Sitemap:» со ссылкой на sitemap.xml этого сайта добавится сама, если её нет.',
        'robots_not_production' => 'Этот сервер не боевой (APP_ENV=:env), поэтому robots.txt запрещает индексацию, что бы здесь ни было написано.',
        'robots_file_exists' => 'Лежит файл public/robots.txt: веб-сервер отдаёт его вместо этого текста. Удалите файл.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» — не имя домена.',
        'domain_taken' => 'Домен :domain уже занят сайтом «:site».',
    ],
];

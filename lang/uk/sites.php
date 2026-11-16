<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Додати сайт',
    'tabs' => [
        'site' => 'Сайт',
        'codes' => 'Лічильники та коди',
    ],
    'add_domain' => 'Додати домен',
    'fields' => [
        'name' => 'Назва',
        'code' => 'Код',
        'domains' => 'Домени',
        'folder' => 'Тека',
        'languages' => 'Мови',
        'default_language' => 'Мова за замовчуванням',
        'active' => 'Активний',
        'is_default' => 'Основний сайт',
        'sort' => 'Сортування',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Лічильник Яндекс Метрика',
        'yandex_webvisor' => 'Вебвізор',
        'counters_skip_admins' => 'Не враховувати адміністраторів',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Яндекс Вебмастер',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Код у <head>',
        'body_start_code' => 'Код на початку <body>',
        'body_end_code' => 'Код перед </body>',
    ],
    'hints' => [
        'code' => 'Малі латинські літери, цифри, дефіси: main, shop, de. Змінити потім не можна.',
        'domains' => 'Сайт відкривається на цих доменах: example.com, www.example.com. Перший — основний.',
        'folder' => 'Для кількох сайтів на одному домені: site2 відкриє цей сайт за адресою example.com/site2/. Порожньо — сайт у корені своїх доменів. Сайт без доменів живе в цій теці на будь-якому домені.',
        'languages' => 'Мови контенту цього сайту. Нічого не вибрано — усі активні мови.',
        'default_language' => 'Сторінки цієї мови відкриваються без мовного префікса в адресі. Порожньо — мова за замовчуванням для всієї панелі.',
        'is_default' => 'Відкривається на будь-якому домені, який не належить іншому сайту, і на localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX для Google Analytics 4 або GTM-XXXXXXX для Tag Manager. Лише ідентифікатор: код лічильника додається на всі сторінки сам.',
        'yandex_metrika_id' => 'Лише номер лічильника з налаштувань Метрики: код додається на всі сторінки сам.',
        'counters_skip_admins' => 'Авторизованим адміністраторам сторінки віддаються без Google Tag і Яндекс Метрика, щоб їхні відвідування не псували статистику. Мета-теги підтвердження та код нижче виводяться завжди.',
        'verification' => 'Код із мета-тега :name. Можна вставити тег повністю.',
        'snippet' => 'Вставляється як є на всі сторінки цього сайту: віджет чату, піксель, будь-який інший лічильник.',
        'robots_txt' => 'Порожньо — роботам можна все. Рядок «Sitemap:» з посиланням на sitemap.xml цього сайту додасться сам, якщо його немає.',
        'robots_not_production' => 'Цей сервер не бойовий (APP_ENV=:env), тому robots.txt забороняє індексацію, що б тут не було написано.',
        'robots_file_exists' => 'Є файл public/robots.txt: вебсервер віддає його замість цього тексту. Видаліть файл.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» не є доменним іменем.',
        'domain_taken' => 'Домен :domain уже використовується сайтом «:site».',
        'folder_invalid' => '«:folder» — не тека: латинські літери, цифри, дефіси та підкреслення, частини через скісну риску.',
        'folder_reserved' => 'Тека :folder — адреса самої панелі.',
        'folder_default' => 'Сайт за замовчуванням відповідає на всі адреси, які не зайняв інший сайт, тому живе в корені. Очистьте теку або зробіть сайтом за замовчуванням інший сайт.',
        'folder_taken' => 'Адреса :address вже зайнята сайтом «:site».',
    ],
];

<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Додати сайт',
    'add_domain' => 'Додати домен',
    'fields' => [
        'name' => 'Назва',
        'code' => 'Код',
        'domains' => 'Домени',
        'languages' => 'Мови',
        'default_language' => 'Мова за замовчуванням',
        'active' => 'Активний',
        'is_default' => 'Основний сайт',
        'sort' => 'Сортування',
    ],
    'hints' => [
        'code' => 'Малі латинські літери, цифри, дефіси: main, shop, de. Змінити потім не можна.',
        'domains' => 'Сайт відкривається на цих доменах: example.com, www.example.com. Перший — основний.',
        'languages' => 'Мови контенту цього сайту. Нічого не вибрано — усі активні мови.',
        'default_language' => 'Сторінки цієї мови відкриваються без мовного префікса в адресі. Порожньо — мова за замовчуванням для всієї панелі.',
        'is_default' => 'Відкривається на будь-якому домені, який не належить іншому сайту, і на localhost.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» не є доменним іменем.',
        'domain_taken' => 'Домен :domain уже використовується сайтом «:site».',
    ],
];

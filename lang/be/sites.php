<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Дадаць сайт',
    'add_domain' => 'Дадаць дамен',
    'fields' => [
        'name' => 'Назва',
        'code' => 'Код',
        'domains' => 'Дамены',
        'languages' => 'Мовы',
        'default_language' => 'Мова па змаўчанні',
        'active' => 'Актыўны',
        'is_default' => 'Асноўны сайт',
        'sort' => 'Сартаванне',
    ],
    'hints' => [
        'code' => 'Малыя лацінскія літары, лічбы, злучкі: main, shop, de. Потым змяніць нельга.',
        'domains' => 'Сайт адкрываецца на гэтых даменах: example.com, www.example.com. Першы — асноўны.',
        'languages' => 'Мовы кантэнту гэтага сайта. Нічога не выбрана — усе актыўныя мовы.',
        'default_language' => 'Старонкі гэтай мовы адкрываюцца без моўнага прэфікса ў адрасе. Пуста — мова па змаўчанні для ўсёй панэлі.',
        'is_default' => 'Адкрываецца на любым дамене, які не належыць іншаму сайту, і на localhost.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» не з’яўляецца даменным імем.',
        'domain_taken' => 'Дамен :domain ужо выкарыстоўваецца сайтам «:site».',
    ],
];

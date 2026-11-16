<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Сайт қосу',
    'add_domain' => 'Домен қосу',
    'fields' => [
        'name' => 'Атауы',
        'code' => 'Код',
        'domains' => 'Домендер',
        'languages' => 'Тілдер',
        'default_language' => 'Әдепкі тіл',
        'active' => 'Белсенді',
        'is_default' => 'Негізгі сайт',
        'sort' => 'Сұрыптау',
    ],
    'hints' => [
        'code' => 'Кіші латын әріптері, сандар, сызықшалар: main, shop, de. Кейін өзгертуге болмайды.',
        'domains' => 'Сайт осы домендерде ашылады: example.com, www.example.com. Біріншісі — негізгісі.',
        'languages' => 'Осы сайттың контент тілдері. Ештеңе таңдалмаса — барлық белсенді тілдер.',
        'default_language' => 'Осы тілдің беттері мекенжайда тіл префиксісіз ашылады. Бос болса — бүкіл панельдің әдепкі тілі.',
        'is_default' => 'Басқа сайтқа тиесілі емес кез келген доменде және localhost-та ашылады.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» домен атауы емес.',
        'domain_taken' => ':domain доменін «:site» сайты бұрыннан пайдаланып жатыр.',
    ],
];

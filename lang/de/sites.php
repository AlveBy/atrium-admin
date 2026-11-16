<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Website hinzufügen',
    'add_domain' => 'Domain hinzufügen',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'domains' => 'Domains',
        'languages' => 'Sprachen',
        'default_language' => 'Standardsprache',
        'active' => 'Aktiv',
        'is_default' => 'Standard-Website',
        'sort' => 'Sortierung',
    ],
    'hints' => [
        'code' => 'Kleinbuchstaben, Ziffern und Bindestriche: main, shop, de. Lässt sich später nicht mehr ändern.',
        'domains' => 'Die Website öffnet sich unter diesen Domains: example.com, www.example.com. Die erste ist die Hauptdomain.',
        'languages' => 'Die Inhaltssprachen dieser Website. Keine ausgewählt – alle aktiven Sprachen.',
        'default_language' => 'Ihre Seiten haben kein Sprachpräfix in der URL. Leer – die Standardsprache des gesamten Panels.',
        'is_default' => 'Wird unter jeder Domain geöffnet, die keine andere Website beansprucht, sowie unter localhost.',
    ],
    'errors' => [
        'domain_invalid' => '„:domain“ ist kein Domainname.',
        'domain_taken' => 'Die Domain :domain wird bereits von der Website „:site“ verwendet.',
    ],
];

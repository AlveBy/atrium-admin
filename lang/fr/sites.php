<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Ajouter un site',
    'add_domain' => 'Ajouter un domaine',
    'fields' => [
        'name' => 'Nom',
        'code' => 'Code',
        'domains' => 'Domaines',
        'languages' => 'Langues',
        'default_language' => 'Langue par défaut',
        'active' => 'Actif',
        'is_default' => 'Site par défaut',
        'sort' => 'Ordre de tri',
    ],
    'hints' => [
        'code' => 'Lettres latines minuscules, chiffres, tirets : main, shop, de. Ne peut pas être modifié par la suite.',
        'domains' => 'Le site s\'ouvre sur ces domaines : example.com, www.example.com. Le premier est le principal.',
        'languages' => 'Les langues de contenu de ce site. Aucune sélectionnée : toutes les langues actives.',
        'default_language' => 'Ses pages n\'ont pas de préfixe de langue dans l\'URL. Vide : la langue par défaut de tout le panneau.',
        'is_default' => 'S\'ouvre sur tout domaine qu\'aucun autre site ne revendique, ainsi que sur localhost.',
    ],
    'errors' => [
        'domain_invalid' => '« :domain » n\'est pas un nom de domaine.',
        'domain_taken' => 'Le domaine :domain est déjà utilisé par le site « :site ».',
    ],
];

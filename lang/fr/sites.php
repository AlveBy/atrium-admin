<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Ajouter un site',
    'tabs' => [
        'site' => 'Site',
        'codes' => 'Compteurs et codes',
    ],
    'add_domain' => 'Ajouter un domaine',
    'fields' => [
        'name' => 'Nom',
        'code' => 'Code',
        'domains' => 'Domaines',
        'folder' => 'Dossier',
        'languages' => 'Langues',
        'default_language' => 'Langue par défaut',
        'active' => 'Actif',
        'is_default' => 'Site par défaut',
        'sort' => 'Ordre de tri',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Compteur Yandex Metrica',
        'yandex_webvisor' => 'Relecture de session (Webvisor)',
        'counters_skip_admins' => 'Ne pas compter les administrateurs',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Code dans <head>',
        'body_start_code' => 'Code au début de <body>',
        'body_end_code' => 'Code avant </body>',
    ],
    'hints' => [
        'code' => 'Lettres latines minuscules, chiffres, tirets : main, shop, de. Ne peut pas être modifié par la suite.',
        'domains' => 'Le site s\'ouvre sur ces domaines : example.com, www.example.com. Le premier est le principal.',
        'folder' => 'Pour plusieurs sites sur un domaine : site2 ouvre ce site à l\'adresse example.com/site2/. Vide : le site est à la racine de ses domaines. Un site sans domaine se trouve dans ce dossier sur tous les domaines.',
        'languages' => 'Les langues de contenu de ce site. Aucune sélectionnée : toutes les langues actives.',
        'default_language' => 'Ses pages n\'ont pas de préfixe de langue dans l\'URL. Vide : la langue par défaut de tout le panneau.',
        'is_default' => 'S\'ouvre sur tout domaine qu\'aucun autre site ne revendique, ainsi que sur localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX pour Google Analytics 4 ou GTM-XXXXXXX pour Tag Manager. Uniquement l\'ID : le code du compteur est ajouté de lui-même à chaque page.',
        'yandex_metrika_id' => 'Uniquement le numéro du compteur indiqué dans les paramètres de Metrica : le code est ajouté de lui-même à chaque page.',
        'counters_skip_admins' => 'Les administrateurs connectés reçoivent des pages sans Google Tag ni Yandex Metrica, pour que leurs propres visites ne faussent pas les statistiques. Les balises de vérification et le code ci-dessous sont toujours présents.',
        'verification' => 'Le code de la balise meta :name. Vous pouvez coller la balise entière.',
        'snippet' => 'Ajouté tel quel à chaque page de ce site : widget de chat, pixel, tout autre compteur.',
        'robots_txt' => 'Vide : les robots peuvent tout indexer. Une ligne « Sitemap: » pointant vers le sitemap.xml de ce site est ajoutée s\'il n\'y en a pas.',
        'robots_not_production' => 'Ce serveur n\'est pas un serveur de production (APP_ENV=:env) : robots.txt interdit donc l\'indexation, quoi qu\'il soit écrit ici.',
        'robots_file_exists' => 'Le fichier public/robots.txt existe : le serveur web le renvoie à la place de ce texte. Supprimez le fichier.',
    ],
    'errors' => [
        'domain_invalid' => '« :domain » n\'est pas un nom de domaine.',
        'domain_taken' => 'Le domaine :domain est déjà utilisé par le site « :site ».',
        'folder_invalid' => '« :folder » n\'est pas un dossier : lettres latines, chiffres, tirets et tirets bas, parties séparées par des barres obliques.',
        'folder_reserved' => 'Le dossier :folder est une adresse du panneau lui-même.',
        'folder_default' => 'Le site par défaut répond à toute adresse qu\'aucun autre site ne revendique, il reste donc à la racine. Videz le dossier ou faites d\'un autre site le site par défaut.',
        'folder_taken' => 'L\'adresse :address est déjà utilisée par le site « :site ».',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Ajouter une langue',
    'fields' => [
        'name' => 'Nom',
        'code' => 'Code',
        'active' => 'Active',
        'is_default' => 'Par défaut',
        'sort' => 'Ordre de tri',
    ],
    'hints' => [
        'name' => 'Tel que les visiteurs le verront dans le sélecteur de langue : English, Deutsch, Español.',
        'code' => 'Deux ou trois lettres minuscules, éventuellement avec une région : en, de, pt-br. Il devient le début de l\'URL (/de/…) et ne pourra plus être modifié.',
        'code_fixed' => 'Les traductions sont enregistrées sous ce code et les URL commencent par lui, il ne peut donc pas être modifié.',
        'is_default' => 'Ses pages n\'ont pas de préfixe de langue dans l\'URL, et son texte s\'affiche partout où une traduction manque.',
    ],
    'switch' => 'Langue du contenu',
];

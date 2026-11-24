<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Journal des modifications',
    'subtitle' => 'Qui a modifié quoi et quand, dans tous les modules que vous pouvez ouvrir.',
    'record_title' => 'Historique : :title',
    'button' => 'Historique',
    'empty' => 'Rien n’a encore été enregistré.',
    'filters' => [
        'module' => 'Module',
        'admin' => 'Administrateur',
        'from' => 'Du',
        'to' => 'Au',
        'all' => 'Tous',
        'apply' => 'Appliquer',
        'reset' => 'Réinitialiser',
    ],
    'events' => [
        'created' => 'Créé',
        'updated' => 'Modifié',
        'deleted' => 'Supprimé',
        'restored' => 'Restauré',
        'purged' => 'Supprimé définitivement',
    ],
    'sources' => [
        'admin' => 'dans le panneau',
        'api' => 'via l’API',
        'import' => 'par import',
        'console' => 'par une commande',
        'schedule' => 'par le planificateur',
    ],
    'system' => 'Système',
    'field' => 'Champ',
    'old' => 'Avant',
    'new' => 'Après',
    'secret' => 'modifié (la valeur n’est pas conservée)',
    'empty_value' => 'vide',
    'yes' => 'oui',
    'no' => 'non',
    'back' => 'Retour à l’enregistrement',
    'versions' => [
        'title' => 'Versions',
        'hint' => 'Ce que contenait le formulaire après chaque enregistrement. Comparez deux versions ou rétablissez une version antérieure.',
        'baseline' => 'Avant le début de l’historique',
        'current' => 'actuelle',
        'from' => 'Comparer',
        'to' => 'avec',
        'compare' => 'Comparer',
        'same' => 'Les deux versions sont identiques.',
        'restore' => 'Rétablir',
        'confirm_title' => 'Rétablir cette version ?',
        'confirm' => 'L’enregistrement reprend les valeurs de cette version. Son contenu actuel reste ici comme une version à part.',
        'restored' => 'La version a été rétablie.',
        'failed' => 'Impossible de rétablir la version : :errors',
    ],
];

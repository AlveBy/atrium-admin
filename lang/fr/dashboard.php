<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Tableau de bord',

    'site_speed' => [
        'title' => 'Vitesse du site',
        'no_data' => 'aucune donnée',
        'unit' => 's',
        'bands' => [
            'very_fast' => 'Très rapide',
            'fast' => 'Rapide',
            'not_fast' => 'Peu rapide',
            'slow' => 'Lent',
            'very_slow' => 'Très lent',
        ],
        'samples_note' => 'médiane de :samples mesures sur :days jours',
        'no_samples' => 'aucune mesure pour l\'instant — les chiffres apparaîtront après les premières visites du site',
    ],

    'performance' => [
        'title' => 'Moniteur de performances',
        'details' => 'Détails',
        'collapse' => 'Réduire',
        'caption' => 'Score actuel',
        'unit' => 'pages par seconde',
        'pending' => 'Le score apparaîtra après les premières visites du site',
        'note' => 'temps de génération moyen :seconds s · :pages pages sur :days jours',
        'slowest_title' => 'Pages les plus lourdes',
        'slowest_row' => ':ms ms · :hits',
    ],

    'recent' => [
        'title' => 'Éléments récents',
        'empty' => 'Aucun élément pour l\'instant.',
        'all' => 'Tous les éléments',
    ],
];

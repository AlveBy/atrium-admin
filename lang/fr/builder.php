<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Éditeur de page : :title',
    'open' => 'Ouvrir l’éditeur de page',
    'back' => 'Retour au formulaire',
    'save_draft' => 'Enregistrer le brouillon',
    'publish' => 'Publier',
    'failed' => 'L’opération a échoué. Vérifiez la connexion et réessayez.',
    'leave' => 'Les modifications ne sont pas enregistrées. Quitter l’éditeur et les perdre ?',
    'stale' => 'La page publiée a changé après le début de ce brouillon. La publication demandera confirmation avant de remplacer ces changements.',
    'hidden' => 'Cette page est désactivée : la publier ne l’affiche pas sur le site.',
    'palette' => 'Blocs',
    'palette_hint' => 'Faites glisser un bloc dans une colonne, ou appuyez dessus pour le placer après la sélection.',
    'add_type' => 'Ajouter un bloc : :type',
    'column_full' => 'Il n’y a plus de place pour un autre bloc ici.',
    'structure' => 'Structure de la page',
    'drop_here' => 'Glissez un bloc ici',
    'has_error' => 'Erreur',
    'not_drawn' => 'Pas encore visible sur la page',
    'settings' => 'Réglages',
    'nothing_selected' => 'Sélectionnez une section, une colonne ou un bloc pour modifier ses réglages.',
    'move_to' => 'Déplacer vers la colonne',
    'move' => 'Déplacer',
    'status' => [
        'published' => 'Publiée',
        'unsaved' => 'Modifications non enregistrées',
        'draft' => 'Brouillon enregistré le :date, non publié',
        'draft_by' => 'Brouillon enregistré le :date par :author, non publié',
    ],
    'notices' => [
        'draft' => 'Le brouillon est enregistré. Le site affiche la page publiée.',
        'publish' => 'Publiée.',
        'discard' => 'Les modifications sont abandonnées.',
        'version' => 'La version est ouverte dans l’éditeur. Publiez-la pour qu’elle apparaisse sur le site.',
    ],
    'discard' => [
        'button' => 'Abandonner les modifications',
        'title' => 'Abandonner les modifications ?',
        'question' => 'Le brouillon et les modifications non enregistrées sont supprimés ; l’éditeur revient à la page publiée.',
    ],
    'conflicts' => [
        'draft' => 'Quelqu’un a enregistré ou supprimé le brouillon de cette page après son ouverture.',
        'published' => 'La page publiée a changé après le début de cette modification.',
    ],
    'conflict' => [
        'title' => 'La page a été modifiée ailleurs',
        'hint' => 'Charger la page plus récente supprime vos modifications non enregistrées. Continuer remplace les autres modifications par les vôtres.',
        'reload' => 'Charger la page plus récente',
        'force' => 'Continuer quand même',
    ],
    'versions' => [
        'button' => 'Versions',
        'title' => 'Versions précédentes de la page',
        'hint' => 'Ouvrir une version ne change rien sur le site tant que vous ne la publiez pas.',
        'replaces' => 'Ouvrir une version remplace vos modifications non enregistrées.',
        'open' => 'Ouvrir dans l’éditeur',
        'current' => 'publiée actuellement',
    ],
    'preview' => [
        'title' => 'Aperçu',
        'updating' => 'Actualisation…',
        'hint' => 'Cliquez sur la page pour en sélectionner une partie.',
        'failed' => 'L’aperçu n’a pas pu être affiché.',
        'selected' => 'Sélection : :place',
    ],
    'said' => [
        'added' => 'Ajouté : :place',
        'moved' => 'Déplacé, désormais : :place',
        'removed' => 'Supprimé : :place',
    ],
    'count' => [
        'sections' => ':count section|:count sections',
        'blocks' => ':count bloc|:count blocs',
        'empty' => 'La page n’a pas encore de blocs.',
    ],
];

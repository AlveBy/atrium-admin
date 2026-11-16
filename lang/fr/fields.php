<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Effacer',

    'content' => [
        'mode_text' => 'Texte',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Éditeur visuel',
    ],

    'editor' => [
        'placeholder' => 'Commencez à écrire…',
        'format' => 'Style de texte',
        'format_paragraph' => 'Texte normal',
        'format_h1' => 'Titre 1',
        'format_h2' => 'Titre 2',
        'format_h3' => 'Titre 3',
        'format_h4' => 'Titre 4',
        'format_h5' => 'Titre 5',
        'format_h6' => 'Titre 6',
        'format_blockquote' => 'Citation',
        'font_size' => 'Taille de police',
        'font_size_default' => 'Taille',
        'font_size_pt' => ':size pt',
        'text_color' => 'Couleur du texte',
        'bold' => 'Gras',
        'bold_shortcut' => 'Gras (Ctrl+B)',
        'italic' => 'Italique',
        'italic_shortcut' => 'Italique (Ctrl+I)',
        'underline' => 'Souligné',
        'underline_shortcut' => 'Souligné (Ctrl+U)',
        'strike' => 'Barré',
        'link' => 'Lien',
        'link_prompt' => 'URL du lien :',
        'bullet_list' => 'Liste à puces',
        'ordered_list' => 'Liste numérotée',
        'heading' => 'Titre',
        'image' => 'Image',
        'file' => 'Fichier (PDF, DOC, XLS)',
        'align_left' => 'Aligner à gauche',
        'align_center' => 'Centrer',
        'align_right' => 'Aligner à droite',
        'align_justify' => 'Justifier',
        'table_insert' => 'Insérer un tableau',
        'table_add_row' => 'Ajouter une ligne',
        'table_delete_row' => 'Supprimer la ligne',
        'table_add_column' => 'Ajouter une colonne',
        'table_delete_column' => 'Supprimer la colonne',
        'table_merge_split' => 'Fusionner ou scinder les cellules',
        'table_delete' => 'Supprimer le tableau',
    ],

    'upload' => [
        'from_library' => 'Depuis la médiathèque',
        'progress' => 'Importation… :percent %',
        'too_big' => 'Le fichier dépasse la taille maximale (:max Ko).',
        'too_large' => 'Le fichier est trop volumineux — le serveur a refusé l\'importation.',
        'network_error' => 'Impossible de joindre le serveur. Vérifiez votre connexion.',
        'aborted' => 'Importation annulée.',
        'no_url' => 'Le serveur n\'a pas renvoyé de lien vers le fichier importé.',
        'http_error' => 'Erreur lors de l\'importation du fichier (HTTP :status).',
        'failed' => 'Impossible d\'importer le fichier.',
        'request_failed' => 'La requête a échoué',
        'session_expired' => 'Votre session a expiré. Actualisez la page et reconnectez-vous.',
    ],

    'gallery' => [
        'add_from_library' => 'Ajouter depuis la médiathèque',
        'counter' => ':current / :count image|:current / :count images',
        'file_too_big' => 'Le fichier :name dépasse la taille maximale de :max Ko',
        'upload_failed' => 'Impossible d\'importer le fichier :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Tout effacer',
        'no_options' => 'Aucune option disponible',
    ],

    'multi_select' => [
        'nothing_found' => 'Aucun résultat',
    ],

    'map' => [
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'open_on_map' => 'Ouvrir sur la carte',
        'parse_error' => 'Impossible de lire les coordonnées. Format attendu : latitude,longitude — par exemple :example',
    ],

    'repeatable' => [
        'item' => 'Élément',
        'item_title' => ':label #:number',
        'empty' => 'Aucun élément. Cliquez sur « :add » pour en ajouter un.',
        'move_up' => 'Monter',
        'move_down' => 'Descendre',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Générer à partir du nom',
        'url_label' => 'URL :',
    ],

    'tree' => [
        'search_placeholder' => 'Rechercher…',
        'top_level' => 'Niveau supérieur',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Nom',
        'code' => 'Code',
        'active' => 'Actif',
        'active_from' => 'Actif à partir du',
        'active_to' => 'Actif jusqu’au',
        'sort' => 'Tri',
        'section' => 'Rubrique',
        'parent_section' => 'Rubrique parente',
        'preview_picture' => 'Image d’aperçu',
        'preview_text' => 'Texte d’aperçu',
        'detail_picture' => 'Image détaillée',
        'detail_text' => 'Texte détaillé',
        'description' => 'Description',
        'picture' => 'Image',
        'created_at' => 'Créé',
        'updated_at' => 'Modifié',
    ],

    'tabs' => [
        'label' => 'Onglets',
        'main' => 'Général',
        'section' => 'Rubrique',
        'seo' => 'SEO',
        'additional' => 'Autres',
        'custom_fields' => 'Champs personnalisés',
        'element_properties' => 'Propriétés des éléments',
        'preview' => 'Aperçu',
        'detail' => 'Détails',
        'sections' => 'Rubriques',
        'description' => 'Description',
    ],

    'validation' => [
        'own_ancestor' => 'Une rubrique ne peut pas être placée dans elle-même ni dans l\'une de ses sous-rubriques.',
    ],
];

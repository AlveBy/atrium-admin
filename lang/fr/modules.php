<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The installed module packages and their settings.
 */
return [
    'title' => 'Modules',
    'index_hint' => 'Les paquets de modules installés sur ce site. Un module qui a des réglages a un bouton pour y accéder.',
    'empty' => 'Aucun paquet de module n’est installé.',
    'module' => 'Module',
    'package' => 'Paquet',
    'settings' => 'Réglages',
    'settings_title' => ':module : réglages',
    'per_site' => 'Ce sont les réglages du site « :site ». Choisissez un autre site dans l’en-tête pour régler le sien.',
    'saved' => 'Réglages enregistrés.',
    'enable' => 'Activer',
    'disable' => 'Désactiver',
    'enabled' => ':module est activé.',
    'disabled' => ':module est désactivé. Ses données et réglages sont conservés.',
    'parent_off' => 'Activez d’abord :parent.',
    'config_locked' => 'Impossible de modifier config/atrium.php.',
    'required_title' => 'Requis, non installés',
    'required_hint' => 'Paquets de composer.json pas encore installés. Pour en installer un :',
    'catalogue_title' => 'Catalogue',
    'catalogue_hint' => 'Modules Atrium publiés sur Packagist. Pour en ajouter un, exécutez dans le dossier du projet :',
    'catalogue_loading' => 'Chargement du catalogue…',
    'catalogue_unavailable' => 'Packagist n’a pas répondu. Réessayez plus tard.',
    'catalogue_empty' => 'Aucun module trouvé sur Packagist.',
    'status_installed' => 'Installé',
    'status_disabled' => 'Désactivé',
    'status_required' => 'Requis',
    'downloads' => ':count téléchargements',
];

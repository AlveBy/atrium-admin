<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'Superadmin',
    'superadmin_hint' => 'Può fare tutto senza ruoli ed è l’unico a gestire i ruoli e gli altri superadmin.',
    'roles' => 'Ruoli',
    'roles_hint' => 'L’amministratore può fare tutto ciò che consente uno qualsiasi dei ruoli selezionati.',
    'forbidden' => 'Il tuo ruolo non lo consente.',
    'last_superadmin' => 'È l’ultimo superadmin attivo. Qualcuno deve restare per gestire i ruoli.',
    'saved' => 'Ruolo salvato.',
    'deleted' => 'Ruolo eliminato.',
    'create_title' => 'Nuovo ruolo',
    'edit_title' => 'Ruolo: :name',
    'name' => 'Nome',
    'permissions' => 'Permessi',
    'section' => 'Sezione',
    'permissions_count' => 'Permessi',
    'users_count' => 'Amministratori',
    'select_all' => 'Seleziona tutto',
    'clear' => 'Deseleziona tutto',
    'all' => 'Tutti',
    'matrix_hint' => '«Visualizza» apre l’elenco e i record in sola lettura. Creare, modificare ed eliminare ne hanno bisogno, quindi viene selezionato insieme a loro.',
    'index_hint' => 'Un ruolo è un insieme di permessi; i ruoli si assegnano agli amministratori nella loro pagina. A un superadmin non serve alcun ruolo: può fare tutto.',
    'empty' => 'Ancora nessun ruolo.',
    'delete_title' => 'Eliminare il ruolo?',
    'delete_confirm' => 'gli amministratori con questo ruolo perderanno ciò che consentiva.',

    'actions' => [
        'view' => 'Visualizza',
        'create' => 'Crea',
        'update' => 'Modifica',
        'delete' => 'Elimina',
        'use' => 'Emettere e usare token',
    ],
];

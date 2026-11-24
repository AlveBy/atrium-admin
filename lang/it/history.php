<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Registro delle modifiche',
    'subtitle' => 'Chi ha modificato cosa e quando, in tutti i moduli che puoi aprire.',
    'record_title' => 'Cronologia: :title',
    'button' => 'Cronologia',
    'empty' => 'Ancora nulla di registrato.',
    'filters' => [
        'module' => 'Modulo',
        'admin' => 'Amministratore',
        'from' => 'Dal',
        'to' => 'Al',
        'all' => 'Tutti',
        'apply' => 'Applica',
        'reset' => 'Azzera',
    ],
    'events' => [
        'created' => 'Creato',
        'updated' => 'Modificato',
        'deleted' => 'Eliminato',
        'restored' => 'Ripristinato',
    ],
    'sources' => [
        'admin' => 'nel pannello',
        'api' => 'tramite API',
        'import' => 'da importazione',
        'console' => 'da un comando',
        'schedule' => 'dallo scheduler',
    ],
    'system' => 'Sistema',
    'field' => 'Campo',
    'old' => 'Prima',
    'new' => 'Ora',
    'secret' => 'modificato (il valore non viene conservato)',
    'empty_value' => 'vuoto',
    'yes' => 'sì',
    'no' => 'no',
    'back' => 'Torna al record',
    'versions' => [
        'title' => 'Versioni',
        'hint' => 'Cosa conteneva il modulo dopo ogni salvataggio. Confronta due versioni o ripristinane una precedente.',
        'baseline' => 'Prima dell’inizio della cronologia',
        'current' => 'attuale',
        'from' => 'Confronta',
        'to' => 'con',
        'compare' => 'Confronta',
        'same' => 'Le due versioni sono uguali.',
        'restore' => 'Ripristina',
        'confirm_title' => 'Ripristinare questa versione?',
        'confirm' => 'Il record riceve i valori di questa versione. Il contenuto attuale resta qui come versione a sé.',
        'restored' => 'La versione è stata ripristinata.',
        'failed' => 'Impossibile ripristinare la versione: :errors',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Editor della pagina: :title',
    'open' => 'Apri l’editor della pagina',
    'back' => 'Torna al modulo',
    'save_draft' => 'Salva bozza',
    'publish' => 'Pubblica',
    'failed' => 'Operazione non riuscita. Controlla la connessione e riprova.',
    'leave' => 'Le modifiche non sono salvate. Uscire dall’editor e perderle?',
    'stale' => 'La pagina pubblicata è cambiata dopo l’inizio di questa bozza. La pubblicazione chiederà conferma prima di sostituire quelle modifiche.',
    'hidden' => 'Questa pagina è disattivata: pubblicarla non la mostra sul sito.',
    'palette' => 'Blocchi',
    'palette_hint' => 'Trascina un blocco in una colonna oppure premilo per metterlo dopo l’elemento selezionato.',
    'add_type' => 'Aggiungi blocco: :type',
    'column_full' => 'Qui non c’è posto per un altro blocco.',
    'structure' => 'Struttura della pagina',
    'drop_here' => 'Trascina qui un blocco',
    'has_error' => 'Errore',
    'not_drawn' => 'Non ancora visibile nella pagina',
    'settings' => 'Impostazioni',
    'nothing_selected' => 'Seleziona una sezione, una colonna o un blocco per cambiarne le impostazioni.',
    'move_to' => 'Sposta nella colonna',
    'move' => 'Sposta',
    'status' => [
        'published' => 'Pubblicata',
        'unsaved' => 'Modifiche non salvate',
        'draft' => 'Bozza salvata il :date, non pubblicata',
        'draft_by' => 'Bozza salvata il :date da :author, non pubblicata',
    ],
    'notices' => [
        'draft' => 'La bozza è salvata. Il sito mostra la pagina pubblicata.',
        'publish' => 'Pubblicata.',
        'discard' => 'Le modifiche sono state annullate.',
        'version' => 'La versione è aperta nell’editor. Pubblicala perché compaia sul sito.',
    ],
    'discard' => [
        'button' => 'Annulla modifiche',
        'title' => 'Annullare le modifiche?',
        'question' => 'La bozza e le modifiche non salvate vengono rimosse; l’editor torna alla pagina pubblicata.',
    ],
    'conflicts' => [
        'draft' => 'Qualcuno ha salvato o eliminato la bozza di questa pagina dopo che l’hai aperta.',
        'published' => 'La pagina pubblicata è cambiata dopo l’inizio di questa modifica.',
    ],
    'conflict' => [
        'title' => 'La pagina è stata modificata altrove',
        'hint' => 'Caricando la pagina più recente le modifiche non salvate vanno perse. Continuando, le tue modifiche sostituiscono le altre.',
        'reload' => 'Carica la pagina più recente',
        'force' => 'Continua comunque',
    ],
    'versions' => [
        'button' => 'Versioni',
        'title' => 'Versioni precedenti della pagina',
        'hint' => 'Aprire una versione non cambia nulla sul sito finché non la pubblichi.',
        'replaces' => 'Aprire una versione sostituisce le modifiche non salvate.',
        'open' => 'Apri nell’editor',
        'current' => 'pubblicata ora',
    ],
    'preview' => [
        'title' => 'Anteprima',
        'updating' => 'Aggiornamento…',
        'hint' => 'Fai clic sulla pagina per selezionarne una parte.',
        'failed' => 'Impossibile mostrare l’anteprima.',
        'selected' => 'Selezionato: :place',
    ],
    'said' => [
        'added' => 'Aggiunto: :place',
        'moved' => 'Spostato, ora in: :place',
        'removed' => 'Rimosso: :place',
    ],
    'count' => [
        'sections' => ':count sezione|:count sezioni',
        'blocks' => ':count blocco|:count blocchi',
        'empty' => 'La pagina non ha ancora blocchi.',
    ],
];

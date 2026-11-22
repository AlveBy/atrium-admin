<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Import of a module's records from CSV and Excel files.
 */
return [
    'title' => 'Importazione',

    'index' => [
        'upload' => 'Carica un file',
        'hint' => 'CSV o Excel (xlsx), fino a :size MB. La prima riga contiene i nomi delle colonne. La codifica (UTF-8 o windows-1251) e il separatore (punto e virgola o virgola) vengono riconosciuti automaticamente. Un file esportato da questo elenco può essere reimportato così com’è.',
        'choose' => 'File da importare',
        'submit' => 'Carica',
        'history' => 'Importazioni precedenti',
        'empty' => 'Non è ancora stato importato nulla.',
        'file' => 'File',
        'status' => 'Stato',
        'result' => 'Esito',
        'counts' => 'creati :created, aggiornati :updated, errori :failed',
        'user' => 'Chi',
        'console' => 'console',
        'date' => 'Quando',
    ],

    'upload' => [
        'file' => 'file',
    ],

    'statuses' => [
        'new' => 'In attesa del controllo',
        'checking' => 'Controllo in corso',
        'checked' => 'Controllato',
        'importing' => 'Importazione in corso',
        'done' => 'Completato',
        'failed' => 'Non riuscito',
        'cancelled' => 'Interrotto',
    ],

    'delimiters' => [
        ';' => 'punto e virgola',
        ',' => 'virgola',
        "\t" => 'tabulazione',
    ],

    'show' => [
        'back' => 'Tutte le importazioni',
        'file' => ':format, :encoding, separatore: :delimiter, :rows righe',
        'workbook' => ':format, :rows righe',
        'checking' => 'Controllo del file…',
        'importing' => 'Importazione in corso…',
        'progress' => ':processed righe su :total',
        'cancel' => 'Interrompi',
        'cancel_confirm' => 'Interrompere? Ciò che è già stato importato resta.',
        'report' => 'Rapporto',
        'check_report' => 'Controllo',
        'checked' => 'Il controllo è terminato: non è stato ancora scritto nulla. Ecco cosa farà l’importazione.',
        'done' => 'L’importazione è terminata.',
        'cancelled' => 'L’importazione è stata interrotta. Ciò che era già stato importato resta.',
        'counts' => [
            'created' => 'Creati',
            'updated' => 'Aggiornati',
            'skipped' => 'Saltati',
            'failed' => 'Errori',
            'nodes' => 'Elementi dell’albero creati',
        ],
        'will' => [
            'created' => 'Verranno creati',
            'updated' => 'Verranno aggiornati',
            'skipped' => 'Verranno saltati',
            'failed' => 'Errori',
            'nodes' => 'Elementi dell’albero da creare',
        ],
        'row' => 'Riga',
        'outcome' => 'Esito',
        'reason' => 'Motivo',
        'skipped_row' => 'saltata',
        'failed_row' => 'errore',
        'unlisted' => 'Altre :count righe sono conteggiate ma non elencate.',
        'run' => 'Importa',
        'run_hint' => 'Le righe con errori vengono escluse; il resto viene importato come ha mostrato il controllo.',
        'mapping' => 'Colonne',
        'mapping_hint' => 'Quale campo riempie ogni colonna, dedotto dai nomi delle colonne. Una cella vuota lascia il campo com’è.',
        'column' => 'Colonna',
        'unnamed' => 'Colonna :number',
        'samples' => 'Nelle prime righe',
        'target' => 'Campo',
        'skip' => '— non importare —',
        'options' => 'Come importare',
        'key_hint' => 'Un record con lo stesso valore viene aggiornato; se non esiste, viene creato.',
        'separator_hint' => 'In un percorso separa un elemento genitore dal suo figlio: «Abbigliamento / Giacche».',
        'check' => 'Controlla',
        'check_again' => 'Controlla di nuovo',
        'check_hint' => 'Il controllo legge tutto il file e non scrive nulla: vedrai quanti record verrebbero creati e aggiornati e in quali righe ci sono errori.',
    ],

    'settings' => [
        'mapping' => 'Colonne',
        'key' => 'Trova i record tramite',
        'mode' => 'Cosa fare',
        'separator' => 'Separatore del percorso',
    ],

    'modes' => [
        'both' => 'Crea nuovi record e aggiorna quelli esistenti',
        'create' => 'Crea solo nuovi record',
        'update' => 'Aggiorna solo i record esistenti',
    ],

    'validation' => [
        'twice' => 'Un campo accetta una sola colonna; scelto più di una volta: :fields.',
        'key' => 'I record vengono trovati tramite «:field», ma nessuna colonna lo riempie.',
    ],

    'failures' => [
        'empty' => 'Il file non contiene righe.',
        'format' => 'Non è né CSV né Excel (xlsx). Un vecchio file .xls si può salvare in Excel come .xlsx o CSV.',
        'unreadable' => 'Il file Excel non può essere letto: potrebbe essere danneggiato o protetto da password.',
        'crashed' => 'L’importazione si è interrotta per un errore del server. L’errore è stato registrato; ciò che era già stato importato resta.',
        'missing' => 'Il file :file non esiste.',
        'download' => 'Non è stato possibile scaricare il file da :url.',
        'column' => 'Nessuna colonna di questo tipo per --map: «:column». Scrivila così: «Colonna=campo».',
        'option' => 'Il modulo non ha l’opzione «:option» per --option.',
        'module' => 'Il modulo «:module» non esiste più.',
        'key' => 'I record non possono essere trovati tramite «:key»: il modulo non ha questo campo.',
    ],

    'problems' => [
        'no_key' => '«:field» è vuoto: il record non può essere trovato né creato.',
        'bad_id' => '«:value» non è un id: deve essere un numero intero maggiore di zero.',
        'exists' => 'Il record «:key» esiste già, e vengono creati solo nuovi record.',
        'missing' => 'Il record «:key» non esiste, e vengono aggiornati solo i record esistenti.',
        'database' => 'Il database ha rifiutato la riga. L’errore è stato registrato.',
        'not_bool' => '«:field»: «:value» non è né sì né no.',
        'not_option' => '«:field»: non esiste l’opzione «:value».',
        'ambiguous_option' => '«:field»: più di un’opzione si chiama «:value».',
        'tree_missing' => '«:field»: non esiste «:value».',
        'tree_ambiguous' => '«:field»: più di uno si chiama «:value»: indica il percorso, «Genitore / :value».',
        'image_url' => 'L’indirizzo dell’immagine non è valido: :url',
        'image_path' => 'Il percorso dell’immagine non è consentito: :path',
        'image_type' => 'Non è un’immagine (JPEG, PNG, GIF, WebP o SVG): :path',
        'image_missing' => 'L’immagine :path non esiste sul sito.',
        'image_host' => 'L’immagine si trova a un indirizzo interno alla rete, che non viene scaricato: :url',
        'image_download' => 'Non è stato possibile scaricare l’immagine: :url',
        'image_large' => 'L’immagine supera :max KB: :url',
    ],

    'console' => [
        'module' => 'Nessun modulo «:module» accetta importazioni. Quelli che le accettano: :modules.',
        'source' => 'Indica il file o il suo URL.',
        'like' => 'Non esiste l’importazione numero :id per questo modulo.',
        'report' => 'Righe: :processed. Creati: :created, aggiornati: :updated, saltati: :skipped, errori: :failed, elementi dell’albero creati: :nodes.',
        'row' => 'Riga :row: :messages',
    ],
];

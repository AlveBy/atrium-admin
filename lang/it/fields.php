<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Cancella',

    'content' => [
        'mode_text' => 'Testo',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Editor visuale',
    ],

    'editor' => [
        'placeholder' => 'Inizia a scrivere…',
        'format' => 'Stile del testo',
        'format_paragraph' => 'Testo normale',
        'format_h1' => 'Titolo 1',
        'format_h2' => 'Titolo 2',
        'format_h3' => 'Titolo 3',
        'format_h4' => 'Titolo 4',
        'format_h5' => 'Titolo 5',
        'format_h6' => 'Titolo 6',
        'format_blockquote' => 'Citazione',
        'font_size' => 'Dimensione carattere',
        'font_size_default' => 'Dimensione',
        'font_size_pt' => ':size pt',
        'text_color' => 'Colore del testo',
        'bold' => 'Grassetto',
        'bold_shortcut' => 'Grassetto (Ctrl+B)',
        'italic' => 'Corsivo',
        'italic_shortcut' => 'Corsivo (Ctrl+I)',
        'underline' => 'Sottolineato',
        'underline_shortcut' => 'Sottolineato (Ctrl+U)',
        'strike' => 'Barrato',
        'link' => 'Link',
        'link_prompt' => 'URL del link:',
        'bullet_list' => 'Elenco puntato',
        'ordered_list' => 'Elenco numerato',
        'heading' => 'Titolo',
        'image' => 'Immagine',
        'file' => 'File (PDF, DOC, XLS)',
        'align_left' => 'Allinea a sinistra',
        'align_center' => 'Allinea al centro',
        'align_right' => 'Allinea a destra',
        'align_justify' => 'Giustifica',
        'table_insert' => 'Inserisci tabella',
        'table_add_row' => 'Aggiungi riga',
        'table_delete_row' => 'Elimina riga',
        'table_add_column' => 'Aggiungi colonna',
        'table_delete_column' => 'Elimina colonna',
        'table_merge_split' => 'Unisci o dividi celle',
        'table_delete' => 'Elimina tabella',
    ],

    'upload' => [
        'from_library' => 'Dalla libreria',
        'progress' => 'Caricamento… :percent%',
        'too_big' => 'Il file supera la dimensione massima (:max KB).',
        'too_large' => 'Il file è troppo grande — il server ha rifiutato il caricamento.',
        'network_error' => 'Impossibile raggiungere il server. Controlla la connessione.',
        'aborted' => 'Caricamento annullato.',
        'no_url' => 'Il server non ha restituito un link al file caricato.',
        'http_error' => 'Errore durante il caricamento del file (HTTP :status).',
        'failed' => 'Impossibile caricare il file.',
        'request_failed' => 'Richiesta non riuscita',
        'session_expired' => 'La sessione è scaduta. Aggiorna la pagina e accedi di nuovo.',
    ],

    'gallery' => [
        'add_from_library' => 'Aggiungi dalla libreria',
        'counter' => ':current / :count immagine|:current / :count immagini',
        'file_too_big' => 'Il file :name supera la dimensione massima di :max KB',
        'upload_failed' => 'Impossibile caricare il file :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Cancella tutto',
        'no_options' => 'Nessuna opzione disponibile',
    ],

    'multi_select' => [
        'nothing_found' => 'Nessun risultato',
    ],

    'map' => [
        'latitude' => 'Latitudine',
        'longitude' => 'Longitudine',
        'open_on_map' => 'Apri sulla mappa',
        'parse_error' => 'Impossibile leggere le coordinate. Formato previsto: latitudine,longitudine — ad esempio :example',
    ],

    'repeatable' => [
        'item' => 'Elemento',
        'item_title' => ':label n. :number',
        'empty' => 'Nessun elemento. Fai clic su “:add” per aggiungerne uno.',
        'move_up' => 'Sposta su',
        'move_down' => 'Sposta giù',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Genera dal nome',
        'url_label' => 'URL:',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Nome',
        'code' => 'Codice',
        'active' => 'Attivo',
        'active_from' => 'Attivo dal',
        'active_to' => 'Attivo fino al',
        'sort' => 'Ordinamento',
        'section' => 'Sezione',
        'parent_section' => 'Sezione padre',
        'preview_picture' => 'Immagine di anteprima',
        'preview_text' => 'Testo di anteprima',
        'detail_picture' => 'Immagine di dettaglio',
        'detail_text' => 'Testo di dettaglio',
        'description' => 'Descrizione',
        'picture' => 'Immagine',
        'created_at' => 'Creato',
        'updated_at' => 'Modificato',
    ],

    'tabs' => [
        'label' => 'Schede',
        'main' => 'Generale',
        'section' => 'Sezione',
        'seo' => 'SEO',
        'additional' => 'Altro',
        'custom_fields' => 'Campi personalizzati',
        'element_properties' => 'Proprietà dell\'elemento',
        'preview' => 'Anteprima',
        'detail' => 'Dettagli',
        'sections' => 'Sezioni',
        'description' => 'Descrizione',
    ],

    'validation' => [
        'own_ancestor' => 'Una sezione non può essere inserita in se stessa o in una propria sottosezione.',
    ],

    'tree' => [
        'search_placeholder' => 'Cerca…',
        'top_level' => 'Livello principale',
        'add' => 'Aggiungi voce',
        'empty' => 'Ancora nessuna voce. Premi «:add» per creare la prima.',
        'untitled' => 'Senza titolo',
        'drag' => 'Trascina per spostare',
        'move_up' => 'Sposta su',
        'move_down' => 'Sposta giù',
        'indent' => 'Sposta dentro la voce sopra',
        'outdent' => 'Sposta fuori dalla voce superiore',
        'add_child' => 'Aggiungi una voce annidata',
        'invalid' => 'L’elenco delle voci è danneggiato. Ricarica la pagina e riprova.',
        'too_many' => 'Non più di :max voci in totale.',
        'too_deep' => 'Le voci possono essere annidate al massimo per :max livelli.',
        'item_error' => 'Voce «:item»: :message',
    ],

    'link' => [
        'none' => 'Nessun link',
        'url' => 'Indirizzo',
        'record' => 'Pagina di un modulo',
        'module' => 'Modulo',
        'search' => 'Cerca per nome o ID…',
        'nothing' => 'Nessun risultato.',
        'failed' => 'Impossibile caricare l’elenco. Riprova.',
        'change' => 'Cambia',
        'invalid_url' => 'Inserisci un indirizzo che inizi con /, http://, https://, mailto: o tel:.',
        'invalid_record' => 'Scegli un record che esiste ancora.',
    ],
];

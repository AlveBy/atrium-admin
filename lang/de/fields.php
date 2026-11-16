<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Leeren',

    'content' => [
        'mode_text' => 'Text',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Visueller Editor',
    ],

    'editor' => [
        'placeholder' => 'Mit der Eingabe beginnen…',
        'format' => 'Textformat',
        'format_paragraph' => 'Normaler Text',
        'format_h1' => 'Überschrift 1',
        'format_h2' => 'Überschrift 2',
        'format_h3' => 'Überschrift 3',
        'format_h4' => 'Überschrift 4',
        'format_h5' => 'Überschrift 5',
        'format_h6' => 'Überschrift 6',
        'format_blockquote' => 'Zitat',
        'font_size' => 'Schriftgröße',
        'font_size_default' => 'Größe',
        'font_size_pt' => ':size pt',
        'text_color' => 'Textfarbe',
        'bold' => 'Fett',
        'bold_shortcut' => 'Fett (Strg+B)',
        'italic' => 'Kursiv',
        'italic_shortcut' => 'Kursiv (Strg+I)',
        'underline' => 'Unterstrichen',
        'underline_shortcut' => 'Unterstrichen (Strg+U)',
        'strike' => 'Durchgestrichen',
        'link' => 'Link',
        'link_prompt' => 'Link-URL:',
        'bullet_list' => 'Aufzählung',
        'ordered_list' => 'Nummerierte Liste',
        'heading' => 'Überschrift',
        'image' => 'Bild',
        'file' => 'Datei (PDF, DOC, XLS)',
        'align_left' => 'Linksbündig',
        'align_center' => 'Zentriert',
        'align_right' => 'Rechtsbündig',
        'align_justify' => 'Blocksatz',
        'table_insert' => 'Tabelle einfügen',
        'table_add_row' => 'Zeile hinzufügen',
        'table_delete_row' => 'Zeile löschen',
        'table_add_column' => 'Spalte hinzufügen',
        'table_delete_column' => 'Spalte löschen',
        'table_merge_split' => 'Zellen verbinden oder teilen',
        'table_delete' => 'Tabelle löschen',
    ],

    'upload' => [
        'from_library' => 'Aus der Mediathek',
        'progress' => 'Wird hochgeladen… :percent%',
        'too_big' => 'Die Datei überschreitet die maximale Größe (:max KB).',
        'too_large' => 'Die Datei ist zu groß – der Server hat den Upload abgelehnt.',
        'network_error' => 'Der Server ist nicht erreichbar. Prüfen Sie Ihre Verbindung.',
        'aborted' => 'Upload abgebrochen.',
        'no_url' => 'Der Server hat keinen Link zur hochgeladenen Datei zurückgegeben.',
        'http_error' => 'Fehler beim Hochladen der Datei (HTTP :status).',
        'failed' => 'Die Datei konnte nicht hochgeladen werden.',
        'request_failed' => 'Die Anfrage ist fehlgeschlagen',
        'session_expired' => 'Ihre Sitzung ist abgelaufen. Laden Sie die Seite neu und melden Sie sich erneut an.',
    ],

    'gallery' => [
        'add_from_library' => 'Aus der Mediathek hinzufügen',
        'counter' => ':current / :count Bild|:current / :count Bilder',
        'file_too_big' => 'Die Datei :name überschreitet die maximale Größe von :max KB',
        'upload_failed' => 'Die Datei :name konnte nicht hochgeladen werden',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Alle entfernen',
        'no_options' => 'Keine Optionen verfügbar',
    ],

    'map' => [
        'latitude' => 'Breitengrad',
        'longitude' => 'Längengrad',
        'open_on_map' => 'Auf der Karte öffnen',
        'parse_error' => 'Die Koordinaten konnten nicht gelesen werden. Erwartetes Format: Breitengrad,Längengrad – zum Beispiel :example',
    ],

    'repeatable' => [
        'item' => 'Eintrag',
        'item_title' => ':label #:number',
        'empty' => 'Keine Einträge. Klicken Sie auf „:add“, um einen hinzuzufügen.',
        'move_up' => 'Nach oben',
        'move_down' => 'Nach unten',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Aus dem Namen erzeugen',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Suchen…',
        'top_level' => 'Oberste Ebene',
    ],

    'tabs' => [
        'label' => 'Tabs',
        'main' => 'Allgemein',
        'section' => 'Bereich',
        'seo' => 'SEO',
        'additional' => 'Weitere',
        'custom_fields' => 'Benutzerdefinierte Felder',
        'element_properties' => 'Eintragseigenschaften',
    ],

    'validation' => [
        'own_ancestor' => 'Ein Bereich kann nicht in sich selbst oder in einem eigenen Unterbereich platziert werden.',
    ],
];

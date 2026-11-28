<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Seiteneditor: :title',
    'open' => 'Seiteneditor öffnen',
    'back' => 'Zurück zum Formular',
    'save_draft' => 'Entwurf speichern',
    'publish' => 'Veröffentlichen',
    'failed' => 'Das hat nicht geklappt. Prüfen Sie die Verbindung und versuchen Sie es noch einmal.',
    'leave' => 'Die Änderungen sind nicht gespeichert. Editor verlassen und sie verwerfen?',
    'stale' => 'Die veröffentlichte Seite wurde nach Beginn dieses Entwurfs geändert. Vor dem Ersetzen dieser Änderungen wird beim Veröffentlichen nachgefragt.',
    'hidden' => 'Diese Seite ist ausgeschaltet: Durch das Veröffentlichen erscheint sie nicht auf der Website.',
    'palette' => 'Blöcke',
    'palette_hint' => 'Ziehen Sie einen Block in eine Spalte oder drücken Sie ihn, um ihn hinter die Auswahl zu setzen.',
    'add_type' => 'Block hinzufügen: :type',
    'column_full' => 'Hier ist kein Platz für einen weiteren Block.',
    'structure' => 'Seitenaufbau',
    'drop_here' => 'Block hierher ziehen',
    'has_error' => 'Fehler',
    'not_drawn' => 'Noch nicht auf der Seite zu sehen',
    'settings' => 'Einstellungen',
    'nothing_selected' => 'Wählen Sie einen Abschnitt, eine Spalte oder einen Block, um die Einstellungen zu ändern.',
    'move_to' => 'In Spalte verschieben',
    'move' => 'Verschieben',
    'status' => [
        'published' => 'Veröffentlicht',
        'unsaved' => 'Nicht gespeicherte Änderungen',
        'draft' => 'Entwurf gespeichert am :date, nicht veröffentlicht',
        'draft_by' => 'Entwurf gespeichert am :date von :author, nicht veröffentlicht',
    ],
    'notices' => [
        'draft' => 'Der Entwurf ist gespeichert. Die Website zeigt die veröffentlichte Seite.',
        'publish' => 'Veröffentlicht.',
        'discard' => 'Die Änderungen sind verworfen.',
        'version' => 'Die Version ist im Editor geöffnet. Veröffentlichen Sie sie, damit sie auf der Website erscheint.',
    ],
    'discard' => [
        'button' => 'Änderungen verwerfen',
        'title' => 'Änderungen verwerfen?',
        'question' => 'Der Entwurf und die nicht gespeicherten Änderungen werden entfernt; der Editor kehrt zur veröffentlichten Seite zurück.',
    ],
    'conflicts' => [
        'draft' => 'Jemand hat den Entwurf dieser Seite gespeichert oder verworfen, nachdem Sie sie geöffnet haben.',
        'published' => 'Die veröffentlichte Seite wurde nach Beginn dieser Bearbeitung geändert.',
    ],
    'conflict' => [
        'title' => 'Die Seite wurde an anderer Stelle geändert',
        'hint' => 'Beim Laden der neueren Seite gehen Ihre nicht gespeicherten Änderungen verloren. Beim Fortfahren ersetzen Ihre Änderungen die anderen.',
        'reload' => 'Neuere Seite laden',
        'force' => 'Trotzdem fortfahren',
    ],
    'versions' => [
        'button' => 'Versionen',
        'title' => 'Frühere Versionen der Seite',
        'hint' => 'Das Öffnen einer Version ändert auf der Website nichts, bis Sie sie veröffentlichen.',
        'replaces' => 'Das Öffnen einer Version ersetzt Ihre nicht gespeicherten Änderungen.',
        'open' => 'Im Editor öffnen',
        'current' => 'jetzt veröffentlicht',
    ],
    'preview' => [
        'title' => 'Vorschau',
        'updating' => 'Wird aktualisiert…',
        'hint' => 'Klicken Sie auf die Seite, um einen Teil davon auszuwählen.',
        'failed' => 'Die Vorschau konnte nicht dargestellt werden.',
        'selected' => 'Ausgewählt: :place',
    ],
    'said' => [
        'added' => 'Hinzugefügt: :place',
        'moved' => 'Verschoben, jetzt: :place',
        'removed' => 'Entfernt: :place',
    ],
    'count' => [
        'sections' => ':count Abschnitt|:count Abschnitte',
        'blocks' => ':count Block|:count Blöcke',
        'empty' => 'Die Seite hat noch keine Blöcke.',
    ],
];

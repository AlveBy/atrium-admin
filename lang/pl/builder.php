<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Edytor strony: :title',
    'open' => 'Otwórz edytor strony',
    'back' => 'Wróć do formularza',
    'save_draft' => 'Zapisz wersję roboczą',
    'publish' => 'Opublikuj',
    'failed' => 'Nie udało się. Sprawdź połączenie i spróbuj ponownie.',
    'leave' => 'Zmiany nie są zapisane. Opuścić edytor i je utracić?',
    'stale' => 'Opublikowana strona zmieniła się po rozpoczęciu tej wersji roboczej. Przed zastąpieniem tych zmian publikacja zapyta o zgodę.',
    'hidden' => 'Ta strona jest wyłączona: po opublikowaniu nie pojawi się w witrynie.',
    'palette' => 'Bloki',
    'palette_hint' => 'Przeciągnij blok do kolumny lub naciśnij go, aby wstawić go za zaznaczonym elementem.',
    'add_type' => 'Dodaj blok: :type',
    'column_full' => 'Nie ma tu miejsca na kolejny blok.',
    'structure' => 'Struktura strony',
    'drop_here' => 'Przeciągnij blok tutaj',
    'has_error' => 'Błąd',
    'not_drawn' => 'Jeszcze niewidoczny na stronie',
    'settings' => 'Ustawienia',
    'nothing_selected' => 'Zaznacz sekcję, kolumnę lub blok, aby zmienić ustawienia.',
    'move_to' => 'Przenieś do kolumny',
    'move' => 'Przenieś',
    'status' => [
        'published' => 'Opublikowana',
        'unsaved' => 'Niezapisane zmiany',
        'draft' => 'Wersja robocza zapisana :date, nieopublikowana',
        'draft_by' => 'Wersja robocza zapisana :date, autor: :author, nieopublikowana',
    ],
    'notices' => [
        'draft' => 'Wersja robocza została zapisana. W witrynie pozostaje opublikowana strona.',
        'publish' => 'Opublikowano.',
        'discard' => 'Zmiany zostały odrzucone.',
        'version' => 'Wersja jest otwarta w edytorze. Opublikuj ją, aby pojawiła się w witrynie.',
    ],
    'discard' => [
        'button' => 'Odrzuć zmiany',
        'title' => 'Odrzucić zmiany?',
        'question' => 'Wersja robocza i niezapisane zmiany zostaną usunięte, a edytor wróci do opublikowanej strony.',
    ],
    'conflicts' => [
        'draft' => 'Po otwarciu strony ktoś zapisał lub usunął jej wersję roboczą.',
        'published' => 'Opublikowana strona zmieniła się po rozpoczęciu tej edycji.',
    ],
    'conflict' => [
        'title' => 'Stronę zmieniono w innym miejscu',
        'hint' => 'Wczytanie nowszej strony usunie niezapisane zmiany. Kontynuowanie zastąpi cudze zmiany Twoimi.',
        'reload' => 'Wczytaj nowszą stronę',
        'force' => 'Kontynuuj mimo to',
    ],
    'versions' => [
        'button' => 'Wersje',
        'title' => 'Wcześniejsze wersje strony',
        'hint' => 'Otwarcie wersji niczego nie zmienia w witrynie, dopóki jej nie opublikujesz.',
        'replaces' => 'Otwarcie wersji zastąpi niezapisane zmiany.',
        'open' => 'Otwórz w edytorze',
        'current' => 'opublikowana teraz',
    ],
    'preview' => [
        'title' => 'Podgląd',
        'updating' => 'Aktualizowanie…',
        'hint' => 'Kliknij stronę, aby zaznaczyć jej część.',
        'failed' => 'Nie udało się wyświetlić podglądu.',
        'selected' => 'Zaznaczono: :place',
    ],
    'said' => [
        'added' => 'Dodano: :place',
        'moved' => 'Przeniesiono, teraz: :place',
        'removed' => 'Usunięto: :place',
    ],
    'count' => [
        'sections' => ':count sekcja|:count sekcje|:count sekcji',
        'blocks' => ':count blok|:count bloki|:count bloków',
        'empty' => 'Strona nie ma jeszcze bloków.',
    ],
];

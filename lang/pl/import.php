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
    'title' => 'Import',

    'index' => [
        'upload' => 'Prześlij plik',
        'hint' => 'CSV lub Excel (xlsx), do :size MB. Pierwszy wiersz zawiera nazwy kolumn. Kodowanie (UTF-8 lub windows-1251) i separator (średnik lub przecinek) są rozpoznawane automatycznie. Plik wyeksportowany z tej listy można zaimportować z powrotem bez zmian.',
        'choose' => 'Plik do importu',
        'submit' => 'Prześlij',
        'history' => 'Poprzednie importy',
        'empty' => 'Nic jeszcze nie zaimportowano.',
        'file' => 'Plik',
        'status' => 'Stan',
        'result' => 'Wynik',
        'counts' => 'utworzono :created, zaktualizowano :updated, błędów :failed',
        'user' => 'Kto',
        'console' => 'konsola',
        'date' => 'Kiedy',
    ],

    'upload' => [
        'file' => 'plik',
    ],

    'statuses' => [
        'new' => 'Czeka na sprawdzenie',
        'checking' => 'Sprawdzanie',
        'checked' => 'Sprawdzony',
        'importing' => 'Importowanie',
        'done' => 'Gotowe',
        'failed' => 'Niepowodzenie',
        'cancelled' => 'Zatrzymany',
    ],

    'delimiters' => [
        ';' => 'średnik',
        ',' => 'przecinek',
        "\t" => 'tabulator',
    ],

    'show' => [
        'back' => 'Wszystkie importy',
        'file' => ':format, :encoding, separator: :delimiter, wierszy: :rows',
        'workbook' => ':format, wierszy: :rows',
        'checking' => 'Sprawdzanie pliku…',
        'importing' => 'Importowanie…',
        'progress' => ':processed z :total wierszy',
        'cancel' => 'Zatrzymaj',
        'cancel_confirm' => 'Zatrzymać? To, co już zaimportowano, zostanie.',
        'report' => 'Raport',
        'check_report' => 'Sprawdzenie',
        'checked' => 'Sprawdzanie zakończone: nic jeszcze nie zapisano. Oto, co zrobi import.',
        'done' => 'Import zakończony.',
        'cancelled' => 'Import został zatrzymany. To, co już zaimportowano, zostało.',
        'counts' => [
            'created' => 'Utworzono',
            'updated' => 'Zaktualizowano',
            'skipped' => 'Pominięto',
            'failed' => 'Błędy',
            'nodes' => 'Utworzone elementy drzewa',
        ],
        'will' => [
            'created' => 'Zostanie utworzonych',
            'updated' => 'Zostanie zaktualizowanych',
            'skipped' => 'Zostanie pominiętych',
            'failed' => 'Błędy',
            'nodes' => 'Elementy drzewa do utworzenia',
        ],
        'row' => 'Wiersz',
        'outcome' => 'Wynik',
        'reason' => 'Powód',
        'skipped_row' => 'pominięty',
        'failed_row' => 'błąd',
        'unlisted' => 'Jeszcze :count wierszy jest policzonych, ale nie ma ich na liście.',
        'run' => 'Importuj',
        'run_hint' => 'Wiersze z błędami zostaną pominięte; reszta zostanie zaimportowana tak, jak pokazało sprawdzenie.',
        'mapping' => 'Kolumny',
        'mapping_hint' => 'Które pole wypełnia każda kolumna, odgadnięte po nazwach kolumn. Pusta komórka zostawia pole bez zmian.',
        'column' => 'Kolumna',
        'unnamed' => 'Kolumna :number',
        'samples' => 'W pierwszych wierszach',
        'target' => 'Pole',
        'skip' => '— nie importuj —',
        'options' => 'Jak importować',
        'key_hint' => 'Rekord o tej samej wartości zostanie zaktualizowany, a brakujący — utworzony.',
        'separator_hint' => 'W ścieżce oddziela element nadrzędny od podrzędnego: „Odzież / Kurtki”.',
        'check' => 'Sprawdź',
        'check_again' => 'Sprawdź ponownie',
        'check_hint' => 'Sprawdzenie czyta cały plik i niczego nie zapisuje: zobaczysz, ile rekordów zostałoby utworzonych i zaktualizowanych oraz w których wierszach są błędy.',
    ],

    'settings' => [
        'mapping' => 'Kolumny',
        'key' => 'Znajdź rekordy według',
        'mode' => 'Co zrobić',
        'separator' => 'Separator ścieżki',
    ],

    'modes' => [
        'both' => 'Twórz nowe rekordy i aktualizuj istniejące',
        'create' => 'Tylko twórz nowe rekordy',
        'update' => 'Tylko aktualizuj istniejące rekordy',
    ],

    'validation' => [
        'twice' => 'Pole przyjmuje tylko jedną kolumnę; wybrano więcej niż raz: :fields.',
        'key' => 'Rekordy są wyszukiwane według „:field”, ale żadna kolumna go nie wypełnia.',
    ],

    'failures' => [
        'empty' => 'Plik nie ma wierszy.',
        'format' => 'To nie jest ani CSV, ani Excel (xlsx). Stary plik .xls można zapisać w Excelu jako .xlsx lub CSV.',
        'unreadable' => 'Nie można odczytać pliku Excel: może być uszkodzony lub chroniony hasłem.',
        'crashed' => 'Import zatrzymał się z powodu błędu na serwerze. Błąd został zapisany w dzienniku; to, co już zaimportowano, zostało.',
        'missing' => 'Nie ma pliku :file.',
        'download' => 'Nie udało się pobrać pliku z :url.',
        'column' => 'Nie ma takiej kolumny dla --map: „:column”. Zapisz ją tak: „Kolumna=pole”.',
        'option' => 'Moduł nie ma opcji „:option” dla --option.',
        'module' => 'Modułu „:module” już nie ma.',
        'key' => 'Nie można znaleźć rekordów według „:key”: moduł nie ma takiego pola.',
    ],

    'problems' => [
        'no_key' => '„:field” jest puste: nie można znaleźć ani utworzyć rekordu.',
        'bad_id' => '„:value” to nie identyfikator: musi to być liczba całkowita większa od zera.',
        'exists' => 'Rekord „:key” już istnieje, a tworzone są tylko nowe.',
        'missing' => 'Rekordu „:key” nie ma, a aktualizowane są tylko istniejące.',
        'database' => 'Baza danych odrzuciła wiersz. Błąd został zapisany w dzienniku.',
        'not_bool' => '„:field”: „:value” to ani tak, ani nie.',
        'not_option' => '„:field”: nie ma opcji „:value”.',
        'ambiguous_option' => '„:field”: więcej niż jedna opcja nazywa się „:value”.',
        'tree_missing' => '„:field”: nie ma „:value”.',
        'tree_ambiguous' => '„:field”: więcej niż jeden element nazywa się „:value”: podaj jego ścieżkę, „Nadrzędny / :value”.',
        'image_url' => 'Adres obrazu jest nieprawidłowy: :url',
        'image_path' => 'Ścieżka obrazu jest niedozwolona: :path',
        'image_type' => 'To nie jest obraz (JPEG, PNG, GIF, WebP lub SVG): :path',
        'image_missing' => 'Obrazu :path nie ma w witrynie.',
        'image_host' => 'Obraz znajduje się pod adresem wewnątrz sieci, który nie jest pobierany: :url',
        'image_download' => 'Nie udało się pobrać obrazu: :url',
        'image_large' => 'Obraz jest większy niż :max KB: :url',
        'image_demo' => 'W wersji demo obrazy nie są pobierane z adresu: :url',
    ],

    'console' => [
        'module' => 'Żaden moduł „:module” nie przyjmuje importu. Te, które przyjmują: :modules.',
        'source' => 'Podaj plik lub jego adres URL.',
        'like' => 'Nie ma importu o numerze :id dla tego modułu.',
        'report' => 'Wiersze: :processed. Utworzono: :created, zaktualizowano: :updated, pominięto: :skipped, błędów: :failed, utworzonych elementów drzewa: :nodes.',
        'row' => 'Wiersz :row: :messages',
    ],
];

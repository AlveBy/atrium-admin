<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Wyczyść',

    'content' => [
        'mode_text' => 'Tekst',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Edytor wizualny',
    ],

    'editor' => [
        'placeholder' => 'Zacznij pisać…',
        'format' => 'Styl tekstu',
        'format_paragraph' => 'Zwykły tekst',
        'format_h1' => 'Nagłówek 1',
        'format_h2' => 'Nagłówek 2',
        'format_h3' => 'Nagłówek 3',
        'format_h4' => 'Nagłówek 4',
        'format_h5' => 'Nagłówek 5',
        'format_h6' => 'Nagłówek 6',
        'format_blockquote' => 'Cytat',
        'font_size' => 'Rozmiar czcionki',
        'font_size_default' => 'Rozmiar',
        'font_size_pt' => ':size pt',
        'text_color' => 'Kolor tekstu',
        'bold' => 'Pogrubienie',
        'bold_shortcut' => 'Pogrubienie (Ctrl+B)',
        'italic' => 'Kursywa',
        'italic_shortcut' => 'Kursywa (Ctrl+I)',
        'underline' => 'Podkreślenie',
        'underline_shortcut' => 'Podkreślenie (Ctrl+U)',
        'strike' => 'Przekreślenie',
        'link' => 'Link',
        'link_prompt' => 'Adres URL linku:',
        'bullet_list' => 'Lista punktowana',
        'ordered_list' => 'Lista numerowana',
        'heading' => 'Nagłówek',
        'image' => 'Obraz',
        'file' => 'Plik (PDF, DOC, XLS)',
        'align_left' => 'Wyrównaj do lewej',
        'align_center' => 'Wyśrodkuj',
        'align_right' => 'Wyrównaj do prawej',
        'align_justify' => 'Wyjustuj',
        'table_insert' => 'Wstaw tabelę',
        'table_add_row' => 'Dodaj wiersz',
        'table_delete_row' => 'Usuń wiersz',
        'table_add_column' => 'Dodaj kolumnę',
        'table_delete_column' => 'Usuń kolumnę',
        'table_merge_split' => 'Scal lub podziel komórki',
        'table_delete' => 'Usuń tabelę',
    ],

    'upload' => [
        'from_library' => 'Z biblioteki',
        'progress' => 'Przesyłanie… :percent%',
        'too_big' => 'Plik przekracza maksymalny rozmiar (:max KB).',
        'too_large' => 'Plik jest za duży — serwer odrzucił przesyłanie.',
        'network_error' => 'Nie można połączyć się z serwerem. Sprawdź połączenie.',
        'aborted' => 'Przesyłanie anulowane.',
        'no_url' => 'Serwer nie zwrócił linku do przesłanego pliku.',
        'http_error' => 'Błąd przesyłania pliku (HTTP :status).',
        'failed' => 'Nie udało się przesłać pliku.',
        'request_failed' => 'Żądanie nie powiodło się',
        'session_expired' => 'Sesja wygasła. Odśwież stronę i zaloguj się ponownie.',
    ],

    'gallery' => [
        'add_from_library' => 'Dodaj z biblioteki',
        'counter' => ':current / :count obraz|:current / :count obrazy|:current / :count obrazów',
        'file_too_big' => 'Plik :name przekracza maksymalny rozmiar :max KB',
        'upload_failed' => 'Nie udało się przesłać pliku :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Wyczyść wszystko',
        'no_options' => 'Brak dostępnych opcji',
    ],

    'multi_select' => [
        'nothing_found' => 'Nic nie znaleziono',
    ],

    'map' => [
        'latitude' => 'Szerokość geograficzna',
        'longitude' => 'Długość geograficzna',
        'open_on_map' => 'Otwórz na mapie',
        'parse_error' => 'Nie udało się odczytać współrzędnych. Oczekiwany format: szerokość,długość — na przykład :example',
    ],

    'repeatable' => [
        'item' => 'Element',
        'item_title' => ':label nr :number',
        'empty' => 'Brak elementów. Kliknij „:add”, aby dodać.',
        'move_up' => 'Przenieś w górę',
        'move_down' => 'Przenieś w dół',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Utwórz z nazwy',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Szukaj…',
        'top_level' => 'Najwyższy poziom',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Nazwa',
        'code' => 'Kod',
        'active' => 'Aktywny',
        'active_from' => 'Aktywny od',
        'active_to' => 'Aktywny do',
        'sort' => 'Sortowanie',
        'section' => 'Sekcja',
        'parent_section' => 'Sekcja nadrzędna',
        'preview_picture' => 'Obraz zapowiedzi',
        'preview_text' => 'Tekst zapowiedzi',
        'detail_picture' => 'Obraz szczegółowy',
        'detail_text' => 'Opis szczegółowy',
        'description' => 'Opis',
        'picture' => 'Obraz',
        'created_at' => 'Utworzono',
        'updated_at' => 'Zmieniono',
    ],

    'tabs' => [
        'label' => 'Karty',
        'main' => 'Ogólne',
        'section' => 'Sekcja',
        'seo' => 'SEO',
        'additional' => 'Dodatkowe',
        'custom_fields' => 'Pola niestandardowe',
        'element_properties' => 'Właściwości elementu',
        'preview' => 'Zapowiedź',
        'detail' => 'Szczegóły',
        'sections' => 'Sekcje',
        'description' => 'Opis',
    ],

    'validation' => [
        'own_ancestor' => 'Sekcji nie można umieścić w niej samej ani w jej podsekcji.',
    ],
];

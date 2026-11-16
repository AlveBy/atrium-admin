<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Ачысціць',

    'content' => [
        'mode_text' => 'Тэкст',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Візуальны рэдактар',
    ],

    'editor' => [
        'placeholder' => 'Пачніце ўводзіць тэкст…',
        'format' => 'Стыль тэксту',
        'format_paragraph' => 'Звычайны тэкст',
        'format_h1' => 'Загаловак 1',
        'format_h2' => 'Загаловак 2',
        'format_h3' => 'Загаловак 3',
        'format_h4' => 'Загаловак 4',
        'format_h5' => 'Загаловак 5',
        'format_h6' => 'Загаловак 6',
        'format_blockquote' => 'Цытата',
        'font_size' => 'Памер шрыфту',
        'font_size_default' => 'Памер',
        'font_size_pt' => ':size пт',
        'text_color' => 'Колер тэксту',
        'bold' => 'Тлусты',
        'bold_shortcut' => 'Тлусты (Ctrl+B)',
        'italic' => 'Курсіў',
        'italic_shortcut' => 'Курсіў (Ctrl+I)',
        'underline' => 'Падкрэслены',
        'underline_shortcut' => 'Падкрэслены (Ctrl+U)',
        'strike' => 'Закрэслены',
        'link' => 'Спасылка',
        'link_prompt' => 'URL спасылкі:',
        'bullet_list' => 'Маркіраваны спіс',
        'ordered_list' => 'Нумараваны спіс',
        'heading' => 'Загаловак',
        'image' => 'Выява',
        'file' => 'Файл (PDF, DOC, XLS)',
        'align_left' => 'Выраўнаваць па левым краі',
        'align_center' => 'Выраўнаваць па цэнтры',
        'align_right' => 'Выраўнаваць па правым краі',
        'align_justify' => 'Выраўнаваць па шырыні',
        'table_insert' => 'Уставіць табліцу',
        'table_add_row' => 'Дадаць радок',
        'table_delete_row' => 'Выдаліць радок',
        'table_add_column' => 'Дадаць слупок',
        'table_delete_column' => 'Выдаліць слупок',
        'table_merge_split' => 'Аб\'яднаць або падзяліць ячэйкі',
        'table_delete' => 'Выдаліць табліцу',
    ],

    'upload' => [
        'from_library' => 'З бібліятэкі',
        'progress' => 'Загрузка… :percent%',
        'too_big' => 'Файл перавышае максімальны памер (:max КБ).',
        'too_large' => 'Файл занадта вялікі — сервер адхіліў загрузку.',
        'network_error' => 'Не ўдалося звязацца з серверам. Праверце падключэнне.',
        'aborted' => 'Загрузка скасавана.',
        'no_url' => 'Сервер не вярнуў спасылку на загружаны файл.',
        'http_error' => 'Памылка загрузкі файла (HTTP :status).',
        'failed' => 'Не ўдалося загрузіць файл.',
        'request_failed' => 'Не ўдалося выканаць запыт',
        'session_expired' => 'Сеанс скончыўся. Абнавіце старонку і ўвайдзіце зноў.',
    ],

    'gallery' => [
        'add_from_library' => 'Дадаць з бібліятэкі',
        'counter' => ':current / :count выява|:current / :count выявы|:current / :count выяў',
        'file_too_big' => 'Файл :name перавышае максімальны памер :max КБ',
        'upload_failed' => 'Не ўдалося загрузіць файл :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Ачысціць усё',
        'no_options' => 'Няма даступных варыянтаў',
    ],

    'multi_select' => [
        'nothing_found' => 'Нічога не знойдзена',
    ],

    'map' => [
        'latitude' => 'Шырата',
        'longitude' => 'Даўгата',
        'open_on_map' => 'Адкрыць на карце',
        'parse_error' => 'Не ўдалося распазнаць каардынаты. Чаканы фармат: шырата,даўгата — напрыклад, :example',
    ],

    'repeatable' => [
        'item' => 'Элемент',
        'item_title' => ':label #:number',
        'empty' => 'Няма элементаў. Націсніце «:add», каб дадаць.',
        'move_up' => 'Перамясціць уверх',
        'move_down' => 'Перамясціць уніз',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Згенераваць з назвы',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Пошук…',
        'top_level' => 'Верхні ўзровень',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Назва',
        'code' => 'Сімвальны код',
        'active' => 'Актыўнасць',
        'active_from' => 'Пачатак актыўнасці',
        'active_to' => 'Канец актыўнасці',
        'sort' => 'Сартаванне',
        'section' => 'Раздзел',
        'parent_section' => 'Бацькоўскі раздзел',
        'preview_picture' => 'Карцінка для анонса',
        'preview_text' => 'Тэкст анонса',
        'detail_picture' => 'Падрабязная карцінка',
        'detail_text' => 'Падрабязнае апісанне',
        'description' => 'Апісанне',
        'picture' => 'Карцінка',
        'created_at' => 'Створаны',
        'updated_at' => 'Зменены',
    ],

    'tabs' => [
        'label' => 'Укладкі',
        'main' => 'Агульнае',
        'section' => 'Раздзел',
        'seo' => 'SEO',
        'additional' => 'Дадаткова',
        'custom_fields' => 'Дадатковыя палі',
        'element_properties' => 'Уласцівасці элементаў',
        'preview' => 'Анонс',
        'detail' => 'Падрабязна',
        'sections' => 'Раздзелы',
        'description' => 'Апісанне',
    ],

    'validation' => [
        'own_ancestor' => 'Раздзел нельга размясціць унутры самога сябе або ўласнага падраздзела.',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Тазарту',

    'content' => [
        'mode_text' => 'Мәтін',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Визуалды редактор',
    ],

    'editor' => [
        'placeholder' => 'Теруді бастаңыз…',
        'format' => 'Мәтін стилі',
        'format_paragraph' => 'Қалыпты мәтін',
        'format_h1' => 'Тақырып 1',
        'format_h2' => 'Тақырып 2',
        'format_h3' => 'Тақырып 3',
        'format_h4' => 'Тақырып 4',
        'format_h5' => 'Тақырып 5',
        'format_h6' => 'Тақырып 6',
        'format_blockquote' => 'Дәйексөз',
        'font_size' => 'Қаріп өлшемі',
        'font_size_default' => 'Өлшем',
        'font_size_pt' => ':size пт',
        'text_color' => 'Мәтін түсі',
        'bold' => 'Жуан',
        'bold_shortcut' => 'Жуан (Ctrl+B)',
        'italic' => 'Курсив',
        'italic_shortcut' => 'Курсив (Ctrl+I)',
        'underline' => 'Асты сызылған',
        'underline_shortcut' => 'Асты сызылған (Ctrl+U)',
        'strike' => 'Сызылған',
        'link' => 'Сілтеме',
        'link_prompt' => 'Сілтеменің URL мекенжайы:',
        'bullet_list' => 'Таңбалауышты тізім',
        'ordered_list' => 'Нөмірленген тізім',
        'heading' => 'Тақырып',
        'image' => 'Сурет',
        'file' => 'Файл (PDF, DOC, XLS)',
        'align_left' => 'Сол жақ бойынша туралау',
        'align_center' => 'Ортасы бойынша туралау',
        'align_right' => 'Оң жақ бойынша туралау',
        'align_justify' => 'Ені бойынша туралау',
        'table_insert' => 'Кесте кірістіру',
        'table_add_row' => 'Жол қосу',
        'table_delete_row' => 'Жолды жою',
        'table_add_column' => 'Баған қосу',
        'table_delete_column' => 'Бағанды жою',
        'table_merge_split' => 'Ұяшықтарды біріктіру немесе бөлу',
        'table_delete' => 'Кестені жою',
    ],

    'upload' => [
        'from_library' => 'Кітапханадан',
        'progress' => 'Жүктелуде… :percent%',
        'too_big' => 'Файл ең үлкен өлшемнен асады (:max KB).',
        'too_large' => 'Файл тым үлкен — сервер жүктеуді қабылдамады.',
        'network_error' => 'Сервермен байланысу мүмкін болмады. Қосылымды тексеріңіз.',
        'aborted' => 'Жүктеу тоқтатылды.',
        'no_url' => 'Сервер жүктелген файлдың сілтемесін қайтармады.',
        'http_error' => 'Файлды жүктеу қатесі (HTTP :status).',
        'failed' => 'Файлды жүктеу мүмкін болмады.',
        'request_failed' => 'Сұрау орындалмады',
        'session_expired' => 'Сеанс мерзімі аяқталды. Бетті жаңартып, қайта кіріңіз.',
    ],

    'gallery' => [
        'add_from_library' => 'Кітапханадан қосу',
        'counter' => ':current / :count сурет',
        'file_too_big' => ':name файлы ең үлкен өлшемнен (:max KB) асады',
        'upload_failed' => ':name файлын жүктеу мүмкін болмады',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Барлығын тазарту',
        'no_options' => 'Қолжетімді нұсқалар жоқ',
    ],

    'multi_select' => [
        'nothing_found' => 'Ештеңе табылмады',
    ],

    'map' => [
        'latitude' => 'Ендік',
        'longitude' => 'Бойлық',
        'open_on_map' => 'Картадан ашу',
        'parse_error' => 'Координаттарды тану мүмкін болмады. Күтілетін пішім: ендік,бойлық — мысалы, :example',
    ],

    'repeatable' => [
        'item' => 'Элемент',
        'item_title' => ':label #:number',
        'empty' => 'Элементтер жоқ. Қосу үшін «:add» түймесін басыңыз.',
        'move_up' => 'Жоғары жылжыту',
        'move_down' => 'Төмен жылжыту',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Атауынан жасау',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Іздеу…',
        'top_level' => 'Жоғарғы деңгей',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Атауы',
        'code' => 'Символдық код',
        'active' => 'Белсенділік',
        'active_from' => 'Белсенділік басталуы',
        'active_to' => 'Белсенділік аяқталуы',
        'sort' => 'Сұрыптау',
        'section' => 'Бөлім',
        'parent_section' => 'Ата-бөлім',
        'preview_picture' => 'Анонс суреті',
        'preview_text' => 'Анонс мәтіні',
        'detail_picture' => 'Толық сурет',
        'detail_text' => 'Толық сипаттама',
        'description' => 'Сипаттама',
        'picture' => 'Сурет',
        'created_at' => 'Құрылған',
        'updated_at' => 'Өзгертілген',
    ],

    'tabs' => [
        'label' => 'Қойындылар',
        'main' => 'Негізгі',
        'section' => 'Бөлім',
        'seo' => 'SEO',
        'additional' => 'Қосымша',
        'custom_fields' => 'Қосымша өрістер',
        'element_properties' => 'Элемент сипаттары',
        'preview' => 'Анонс',
        'detail' => 'Толығырақ',
        'sections' => 'Бөлімдер',
        'description' => 'Сипаттама',
    ],

    'validation' => [
        'own_ancestor' => 'Бөлімді өзінің ішіне немесе өз ішкі бөліміне орналастыруға болмайды.',
    ],
];

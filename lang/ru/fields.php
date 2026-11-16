<?php

declare(strict_types=1);

/*
 * Form fields, the WYSIWYG editor and form tabs.
 */
return [
    'clear' => 'Очистить',

    'content' => [
        'mode_text' => 'Текст',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Визуальный редактор',
    ],

    'editor' => [
        'placeholder' => 'Начните вводить текст...',
        'format' => 'Стиль текста',
        'format_paragraph' => 'Обычный текст',
        'format_h1' => 'Заголовок 1',
        'format_h2' => 'Заголовок 2',
        'format_h3' => 'Заголовок 3',
        'format_h4' => 'Заголовок 4',
        'format_h5' => 'Заголовок 5',
        'format_h6' => 'Заголовок 6',
        'format_blockquote' => 'Цитата',
        'font_size' => 'Размер шрифта',
        'font_size_default' => 'Размер',
        'font_size_pt' => ':size пт',
        'text_color' => 'Цвет текста',
        'bold' => 'Жирный',
        'bold_shortcut' => 'Жирный (Ctrl+B)',
        'italic' => 'Курсив',
        'italic_shortcut' => 'Курсив (Ctrl+I)',
        'underline' => 'Подчеркнутый',
        'underline_shortcut' => 'Подчеркнутый (Ctrl+U)',
        'strike' => 'Зачеркнутый',
        'link' => 'Ссылка',
        'link_prompt' => 'URL ссылки:',
        'bullet_list' => 'Маркированный список',
        'ordered_list' => 'Нумерованный список',
        'heading' => 'Заголовок',
        'image' => 'Изображение',
        'file' => 'Файл (PDF, DOC, XLS)',
        'align_left' => 'По левому краю',
        'align_center' => 'По центру',
        'align_right' => 'По правому краю',
        'align_justify' => 'По ширине страницы',
        'table_insert' => 'Вставить таблицу',
        'table_add_row' => 'Добавить строку',
        'table_delete_row' => 'Удалить строку',
        'table_add_column' => 'Добавить столбец',
        'table_delete_column' => 'Удалить столбец',
        'table_merge_split' => 'Объединить или разделить ячейки',
        'table_delete' => 'Удалить таблицу',
    ],

    'upload' => [
        'from_library' => 'Из библиотеки',
        'progress' => 'Загрузка… :percent%',
        'too_big' => 'Файл больше допустимого размера (:max КБ).',
        'too_large' => 'Файл слишком большой — сервер отклонил загрузку.',
        'network_error' => 'Не удалось связаться с сервером. Проверьте соединение.',
        'aborted' => 'Загрузка отменена.',
        'no_url' => 'Сервер не вернул ссылку на загруженный файл.',
        'http_error' => 'Ошибка загрузки файла (HTTP :status).',
        'failed' => 'Не удалось загрузить файл.',
        'request_failed' => 'Запрос не удался',
        'session_expired' => 'Сессия истекла. Обновите страницу и войдите заново.',
    ],

    'gallery' => [
        'add_from_library' => 'Добавить из библиотеки',
        'counter' => ':current / :count изображение|:current / :count изображения|:current / :count изображений',
        'file_too_big' => 'Файл :name превышает максимальный размер :max КБ',
        'upload_failed' => 'Не удалось загрузить файл :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Очистить все',
        'no_options' => 'Нет доступных вариантов',
    ],

    'map' => [
        'latitude' => 'Широта',
        'longitude' => 'Долгота',
        'open_on_map' => 'Открыть на карте',
        'parse_error' => 'Не удалось разобрать координаты. Ожидается формат: широта,долгота — например :example',
    ],

    'repeatable' => [
        'item' => 'Элемент',
        'item_title' => ':label #:number',
        'empty' => 'Нет элементов. Нажмите «:add», чтобы добавить.',
        'move_up' => 'Переместить вверх',
        'move_down' => 'Переместить вниз',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Сгенерировать из названия',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Поиск...',
        'top_level' => 'Верхний уровень',
    ],

    'tabs' => [
        'label' => 'Вкладки',
        'main' => 'Основное',
        'section' => 'Раздел',
        'seo' => 'SEO',
        'additional' => 'Дополнительно',
        'custom_fields' => 'Доп. поля',
        'element_properties' => 'Свойства элементов',
    ],

    'validation' => [
        'own_ancestor' => 'Раздел нельзя положить внутрь себя или своего подраздела.',
    ],
];

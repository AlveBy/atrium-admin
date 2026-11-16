<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Очистити',

    'content' => [
        'mode_text' => 'Текст',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Візуальний редактор',
    ],

    'editor' => [
        'placeholder' => 'Почніть вводити текст…',
        'format' => 'Стиль тексту',
        'format_paragraph' => 'Звичайний текст',
        'format_h1' => 'Заголовок 1',
        'format_h2' => 'Заголовок 2',
        'format_h3' => 'Заголовок 3',
        'format_h4' => 'Заголовок 4',
        'format_h5' => 'Заголовок 5',
        'format_h6' => 'Заголовок 6',
        'format_blockquote' => 'Цитата',
        'font_size' => 'Розмір шрифту',
        'font_size_default' => 'Розмір',
        'font_size_pt' => ':size пт',
        'text_color' => 'Колір тексту',
        'bold' => 'Жирний',
        'bold_shortcut' => 'Жирний (Ctrl+B)',
        'italic' => 'Курсив',
        'italic_shortcut' => 'Курсив (Ctrl+I)',
        'underline' => 'Підкреслений',
        'underline_shortcut' => 'Підкреслений (Ctrl+U)',
        'strike' => 'Закреслений',
        'link' => 'Посилання',
        'link_prompt' => 'URL посилання:',
        'bullet_list' => 'Маркований список',
        'ordered_list' => 'Нумерований список',
        'heading' => 'Заголовок',
        'image' => 'Зображення',
        'file' => 'Файл (PDF, DOC, XLS)',
        'align_left' => 'Вирівняти ліворуч',
        'align_center' => 'Вирівняти по центру',
        'align_right' => 'Вирівняти праворуч',
        'align_justify' => 'Вирівняти за шириною',
        'table_insert' => 'Вставити таблицю',
        'table_add_row' => 'Додати рядок',
        'table_delete_row' => 'Видалити рядок',
        'table_add_column' => 'Додати стовпець',
        'table_delete_column' => 'Видалити стовпець',
        'table_merge_split' => 'Об\'єднати або розділити клітинки',
        'table_delete' => 'Видалити таблицю',
    ],

    'upload' => [
        'from_library' => 'З бібліотеки',
        'progress' => 'Завантаження… :percent%',
        'too_big' => 'Файл перевищує максимальний розмір (:max КБ).',
        'too_large' => 'Файл завеликий — сервер відхилив завантаження.',
        'network_error' => 'Не вдалося з\'єднатися із сервером. Перевірте підключення.',
        'aborted' => 'Завантаження скасовано.',
        'no_url' => 'Сервер не повернув посилання на завантажений файл.',
        'http_error' => 'Помилка завантаження файлу (HTTP :status).',
        'failed' => 'Не вдалося завантажити файл.',
        'request_failed' => 'Не вдалося виконати запит',
        'session_expired' => 'Сеанс завершився. Оновіть сторінку й увійдіть знову.',
    ],

    'gallery' => [
        'add_from_library' => 'Додати з бібліотеки',
        'counter' => ':current / :count зображення|:current / :count зображення|:current / :count зображень',
        'file_too_big' => 'Файл :name перевищує максимальний розмір :max КБ',
        'upload_failed' => 'Не вдалося завантажити файл :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Очистити все',
        'no_options' => 'Немає доступних варіантів',
    ],

    'multi_select' => [
        'nothing_found' => 'Нічого не знайдено',
    ],

    'map' => [
        'latitude' => 'Широта',
        'longitude' => 'Довгота',
        'open_on_map' => 'Відкрити на карті',
        'parse_error' => 'Не вдалося розпізнати координати. Очікуваний формат: широта,довгота — наприклад, :example',
    ],

    'repeatable' => [
        'item' => 'Елемент',
        'item_title' => ':label #:number',
        'empty' => 'Немає елементів. Натисніть «:add», щоб додати.',
        'move_up' => 'Перемістити вгору',
        'move_down' => 'Перемістити вниз',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Згенерувати з назви',
        'url_label' => 'URL:',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Назва',
        'code' => 'Символьний код',
        'active' => 'Активність',
        'active_from' => 'Початок активності',
        'active_to' => 'Кінець активності',
        'sort' => 'Сортування',
        'section' => 'Розділ',
        'parent_section' => 'Батьківський розділ',
        'preview_picture' => 'Зображення анонсу',
        'preview_text' => 'Текст анонсу',
        'detail_picture' => 'Детальне зображення',
        'detail_text' => 'Детальний опис',
        'description' => 'Опис',
        'picture' => 'Зображення',
        'created_at' => 'Створено',
        'updated_at' => 'Змінено',
    ],

    'tabs' => [
        'label' => 'Вкладки',
        'main' => 'Загальне',
        'section' => 'Розділ',
        'seo' => 'SEO',
        'additional' => 'Додатково',
        'custom_fields' => 'Додаткові поля',
        'element_properties' => 'Властивості елементів',
        'preview' => 'Анонс',
        'detail' => 'Детально',
        'sections' => 'Розділи',
        'description' => 'Опис',
    ],

    'validation' => [
        'own_ancestor' => 'Розділ не можна розмістити всередині самого себе або власного підрозділу.',
    ],

    'tree' => [
        'search_placeholder' => 'Пошук…',
        'top_level' => 'Верхній рівень',
        'add' => 'Додати пункт',
        'empty' => 'Пунктів поки немає. Натисніть «:add», щоб створити перший.',
        'untitled' => 'Без назви',
        'drag' => 'Перетягніть, щоб перемістити',
        'move_up' => 'Вище',
        'move_down' => 'Нижче',
        'indent' => 'Вкласти в пункт вище',
        'outdent' => 'Винести з батьківського пункту',
        'add_child' => 'Додати вкладений пункт',
        'invalid' => 'Список пунктів пошкоджено. Оновіть сторінку й спробуйте ще раз.',
        'too_many' => 'Усього пунктів — не більше :max.',
        'too_deep' => 'Глибина вкладеності — не більше :max.',
        'item_error' => 'Пункт «:item»: :message',
    ],

    'link' => [
        'none' => 'Без посилання',
        'url' => 'Адреса',
        'record' => 'Сторінка модуля',
        'module' => 'Модуль',
        'search' => 'Знайти за назвою або ID…',
        'nothing' => 'Нічого не знайдено.',
        'failed' => 'Не вдалося завантажити список. Спробуйте ще раз.',
        'change' => 'Змінити',
        'invalid_url' => 'Вкажіть адресу, що починається з /, http://, https://, mailto: або tel:.',
        'invalid_record' => 'Виберіть запис, який ще існує.',
    ],
];

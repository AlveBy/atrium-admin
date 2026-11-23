<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Generic module pages: list, create and edit forms, the data table and the
 * standard SEO fields.
 */
return [
    'created' => 'Запись успешно создана.',
    'updated' => 'Запись успешно обновлена.',
    'deleted' => 'Запись успешно удалена.',
    'cannot_delete' => 'Эту запись нельзя удалить.',
    'delete_failed' => 'Ошибка при удалении записи.',
    'add_item' => 'Добавить элемент',

    'title' => [
        'create' => 'Создать :label',
        'edit' => 'Редактировать: :title',
    ],

    'index' => [
        'total' => 'Всего записей: :count',
        'search_placeholder' => 'Поиск...',
        'search_button' => 'Найти',
    ],

    'filters' => [
        'title' => 'Фильтры',
        'any' => 'Все',
        'yes' => 'Да',
        'no' => 'Нет',
        'reset' => 'Сбросить фильтры',
    ],

    'form' => [
        'breadcrumb_create' => 'Создание',
        'empty_tab' => 'Нет полей для отображения',
        'read_only' => 'Только просмотр: ваша роль позволяет смотреть эту запись, но не менять её.',
    ],

    'delete' => [
        'title' => 'Подтверждение удаления',
        'confirm' => 'Вы уверены, что хотите удалить эту запись?',
    ],

    'table' => [
        'empty' => 'Нет данных',
    ],

    'pagination' => [
        'showing' => 'Показано :from - :to из :total',
    ],

    'seo' => [
        'title' => 'META TITLE',
        'title_hint' => 'Заголовок вкладки браузера. Оставьте пустым — подставится название',
        'keywords' => 'META KEYWORDS',
        'keywords_hint' => 'Ключевые слова через запятую',
        'description' => 'META DESCRIPTION',
        'description_hint' => 'Описание для поисковой выдачи. Оставьте пустым — возьмётся из анонса',
        'page_title' => 'Заголовок элемента (H1)',
        'page_title_hint' => 'Заголовок на самой странице. Оставьте пустым — подставится название',
        'preview_picture_alt' => 'Картинка анонса: ALT',
        'preview_picture_title' => 'Картинка анонса: TITLE',
        'preview_picture_file_name' => 'Картинка анонса: имя файла',
        'detail_picture_alt' => 'Детальная картинка: ALT',
        'detail_picture_title' => 'Детальная картинка: TITLE',
        'detail_picture_file_name' => 'Детальная картинка: имя файла',
        'tags' => 'Теги',
        'tags_hint' => 'Слова или словосочетания через запятую',
        'og_image' => 'OG изображение',
        'og_image_hint' => 'Картинка для превью при репосте в соцсети',
    ],
];

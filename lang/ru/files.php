<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * File manager (media library) and uploads.
 */
return [
    'title' => 'Медиабиблиотека',
    'filters' => [
        'all' => 'Все',
        'images' => 'Изображения',
        'documents' => 'Документы',
    ],
    'all_collections' => 'Все коллекции',
    'search_placeholder' => 'Поиск файлов...',
    'uploading' => 'Загрузка файлов...',
    'empty' => 'Файлы не найдены',
    'load_more' => 'Загрузить ещё',
    'select_hint' => 'Выберите файл или загрузите новый',
    'insert' => 'Вставить',
    'remove_confirm' => 'Убрать ":name" из библиотеки?',

    'errors' => [
        'required' => 'Выберите файл для загрузки.',
        'upload_broken' => 'Файл не загрузился. Попробуйте ещё раз.',
        'too_big' => 'Файл слишком большой. Максимальный размер: :max МБ.',
        'list_failed' => 'Ошибка загрузки списка файлов',
        'upload_failed' => 'Не удалось загрузить файл :name',
        'remove_failed' => 'Не удалось убрать «:name» из библиотеки',
        'delete_failed' => 'Ошибка удаления файла',
        'type_not_allowed' => 'Недопустимый тип файла. Разрешены: :types',
        'content_mismatch' => 'Содержимое файла не соответствует расширению (:type).',
        'not_found' => 'Файл не найден.',
    ],

    'removed' => 'Файл удалён из библиотеки.',
    'uploads_collection' => 'Загруженные из админки',
];

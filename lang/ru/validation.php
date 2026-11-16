<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Сообщения для правил проверки, которыми пользуется панель. Заполняют только
 * пробелы: строка из lang/{locale}/validation.php приложения важнее.
 * :attribute — подпись поля в форме.
 */
return [
    'accepted' => 'Нужно отметить поле «:attribute».',
    'array' => 'Поле «:attribute» должно быть списком.',
    'boolean' => 'Поле «:attribute» должно быть «да» или «нет».',
    'confirmed' => 'Поле «:attribute» не совпадает с подтверждением.',
    'current_password' => 'Неверный пароль.',
    'date' => 'В поле «:attribute» должна быть дата.',
    'date_format' => 'Поле «:attribute» должно быть в формате :format.',
    'distinct' => 'В поле «:attribute» есть повторяющиеся значения.',
    'email' => 'В поле «:attribute» должен быть адрес электронной почты.',
    'exists' => 'Выбранное значение поля «:attribute» не найдено.',
    'file' => 'В поле «:attribute» должен быть файл.',
    'image' => 'В поле «:attribute» должна быть картинка.',
    'in' => 'Выбранное значение поля «:attribute» недопустимо.',
    'integer' => 'В поле «:attribute» должно быть целое число.',
    'json' => 'В поле «:attribute» должен быть корректный JSON.',
    'max' => [
        'array' => 'В поле «:attribute» может быть не больше :max элементов.',
        'file' => 'Файл в поле «:attribute» должен быть не больше :max КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не больше :max.',
        'string' => 'В поле «:attribute» может быть не больше :max символов.',
    ],
    'mimes' => 'Файл в поле «:attribute» должен быть одного из типов: :values.',
    'mimetypes' => 'Файл в поле «:attribute» должен быть одного из типов: :values.',
    'min' => [
        'array' => 'В поле «:attribute» должно быть не меньше :min элементов.',
        'file' => 'Файл в поле «:attribute» должен быть не меньше :min КБ.',
        'numeric' => 'Значение поля «:attribute» должно быть не меньше :min.',
        'string' => 'В поле «:attribute» должно быть не меньше :min символов.',
    ],
    'numeric' => 'В поле «:attribute» должно быть число.',
    'regex' => 'Поле «:attribute» заполнено в неверном формате.',
    'required' => 'Заполните поле «:attribute».',
    'required_if' => 'Заполните поле «:attribute», когда «:other» — :value.',
    'required_with' => 'Заполните поле «:attribute», когда указано :values.',
    'same' => 'Поле «:attribute» должно совпадать с «:other».',
    'string' => 'В поле «:attribute» должен быть текст.',
    'unique' => 'Такое значение поля «:attribute» уже занято.',
    'url' => 'В поле «:attribute» должен быть адрес ссылки.',
    'uploaded' => 'Не удалось загрузить файл в поле «:attribute».',
    'after' => 'В поле «:attribute» должна быть дата позже :date.',
    'after_or_equal' => 'В поле «:attribute» должна быть дата не раньше :date.',
    'before' => 'В поле «:attribute» должна быть дата раньше :date.',
    'before_or_equal' => 'В поле «:attribute» должна быть дата не позже :date.',
    'lowercase' => 'Поле «:attribute» должно быть строчными буквами.',
    'alpha_dash' => 'В поле «:attribute» допустимы только буквы, цифры, дефис и подчёркивание.',
    'password' => [
        'letters' => 'В поле «:attribute» должна быть хотя бы одна буква.',
        'mixed' => 'В поле «:attribute» должны быть заглавные и строчные буквы.',
        'numbers' => 'В поле «:attribute» должна быть хотя бы одна цифра.',
        'symbols' => 'В поле «:attribute» должен быть хотя бы один символ.',
        'uncompromised' => 'Такой «:attribute» уже встречался в утечках данных. Выберите другой.',
    ],
];

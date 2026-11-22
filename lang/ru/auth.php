<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Signing in with a password. The second step lives in alveby/atrium-2fa.
 */
return [
    'failed' => 'Неверный email или пароль.',
    'throttle' => 'Слишком много попыток входа. Попробуйте через :seconds с.',
    'deactivated' => 'Ваш аккаунт деактивирован.',

    'login' => [
        'title' => 'Вход',
        'subtitle' => 'Войдите в панель управления',
        'email' => 'Email',
        'password' => 'Пароль',
        'remember' => 'Запомнить меня',
        'submit' => 'Войти',
        'submitting' => 'Вход...',
        'or' => 'или',
        'demo_title' => 'Публичная демонстрация',
        'demo_text' => 'Войдите с данными ниже: они уже подставлены. Всё, что вы здесь измените, время от времени сбрасывается.',
    ],

    'validation' => [
        'email_required' => 'Email обязателен для заполнения.',
        'email_invalid' => 'Введите корректный email.',
        'password_required' => 'Пароль обязателен для заполнения.',
    ],
];

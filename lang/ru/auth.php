<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Signing in: the password step, the e-mailed code and the e-mail itself.
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
    ],

    'validation' => [
        'email_required' => 'Email обязателен для заполнения.',
        'email_invalid' => 'Введите корректный email.',
        'password_required' => 'Пароль обязателен для заполнения.',
    ],

    'two_factor' => [
        'title' => 'Подтверждение входа',
        'code_sent_to' => 'Мы отправили код на :email',
        'code' => 'Код из письма',
        'submit' => 'Подтвердить',
        'submitting' => 'Проверяем...',
        'resend' => 'Отправить код ещё раз',
        'resend_in' => 'Отправить ещё раз через :seconds с',
        'switch_user' => 'Войти другим пользователем',
        'code_required' => 'Введите код из письма.',
        'code_invalid' => 'Код не подошёл или устарел. Проверьте письмо или запросите новый.',
        'resend_too_soon' => 'Письмо уже отправлено. Новое можно запросить через :seconds с.',
        'resent' => 'Код отправлен ещё раз.',
        'throttle' => 'Слишком много попыток. Попробуйте через :seconds с.',
        'mail_failed' => 'Код подтверждения не удалось отправить: почта недоступна. Проверьте настройки отправки почты.',
    ],

    'mail' => [
        'subject' => ':code — код для входа в админку',
        'title' => 'Код для входа в админку',
        'heading' => 'Вход в панель управления',
        'code_label' => 'Код подтверждения:',
        'valid_for' => 'Код действует :count минуту.|Код действует :count минуты.|Код действует :count минут.',
        'not_you' => 'Если вы не входили в панель управления, код никому не сообщайте и смените пароль — кто-то знает его.',
    ],
];

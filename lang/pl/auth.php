<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Podane dane logowania są nieprawidłowe.',
    'throttle' => 'Zbyt wiele prób logowania. Spróbuj ponownie za :seconds s.',
    'deactivated' => 'Konto jest dezaktywowane.',

    'login' => [
        'title' => 'Logowanie',
        'subtitle' => 'Zaloguj się do panelu administracyjnego',
        'email' => 'E-mail',
        'password' => 'Hasło',
        'remember' => 'Zapamiętaj mnie',
        'submit' => 'Zaloguj się',
        'submitting' => 'Logowanie…',
    ],

    'validation' => [
        'email_required' => 'Adres e-mail jest wymagany.',
        'email_invalid' => 'Wpisz prawidłowy adres e-mail.',
        'password_required' => 'Hasło jest wymagane.',
    ],

    'two_factor' => [
        'title' => 'Potwierdź logowanie',
        'code_sent_to' => 'Wysłaliśmy kod na adres :email',
        'code' => 'Kod z wiadomości e-mail',
        'submit' => 'Potwierdź',
        'submitting' => 'Sprawdzanie…',
        'resend' => 'Wyślij kod ponownie',
        'resend_in' => 'Wyślij ponownie za :seconds s',
        'switch_user' => 'Zaloguj się jako inny użytkownik',
        'code_required' => 'Wpisz kod z wiadomości e-mail.',
        'code_invalid' => 'Kod jest nieprawidłowy lub wygasł. Sprawdź wiadomość e-mail lub poproś o nowy kod.',
        'resend_too_soon' => 'Wiadomość została już wysłana. Nowy kod można zamówić za :seconds s.',
        'resent' => 'Kod został wysłany ponownie.',
        'throttle' => 'Zbyt wiele prób. Spróbuj ponownie za :seconds s.',
        'mail_failed' => 'Nie udało się wysłać kodu potwierdzenia: poczta jest niedostępna. Sprawdź ustawienia poczty.',
    ],

    'mail' => [
        'subject' => ':code to kod logowania do panelu administracyjnego',
        'title' => 'Kod logowania do panelu administracyjnego',
        'heading' => 'Logowanie do panelu administracyjnego',
        'code_label' => 'Kod potwierdzenia:',
        'valid_for' => 'Kod jest ważny przez :count minutę.|Kod jest ważny przez :count minuty.|Kod jest ważny przez :count minut.',
        'not_you' => 'Jeśli to nie Ty próbujesz zalogować się do panelu administracyjnego, nie udostępniaj nikomu tego kodu i zmień hasło: ktoś je zna.',
    ],
];

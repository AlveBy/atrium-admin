<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Diese Anmeldedaten stimmen nicht mit unseren Daten überein.',
    'throttle' => 'Zu viele Anmeldeversuche. Versuchen Sie es in :seconds s erneut.',
    'deactivated' => 'Ihr Konto ist deaktiviert.',

    'login' => [
        'title' => 'Anmelden',
        'subtitle' => 'Beim Admin-Bereich anmelden',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'remember' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
        'submitting' => 'Anmeldung läuft…',
    ],

    'validation' => [
        'email_required' => 'Die E-Mail-Adresse ist erforderlich.',
        'email_invalid' => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'password_required' => 'Das Passwort ist erforderlich.',
    ],

    'two_factor' => [
        'title' => 'Anmeldung bestätigen',
        'code_sent_to' => 'Wir haben einen Code an :email gesendet',
        'code' => 'Code aus der E-Mail',
        'submit' => 'Bestätigen',
        'submitting' => 'Wird geprüft…',
        'resend' => 'Code erneut senden',
        'resend_in' => 'Erneut senden in :seconds s',
        'switch_user' => 'Als anderer Benutzer anmelden',
        'code_required' => 'Geben Sie den Code aus der E-Mail ein.',
        'code_invalid' => 'Der Code ist falsch oder abgelaufen. Prüfen Sie die E-Mail oder fordern Sie einen neuen Code an.',
        'resend_too_soon' => 'Die E-Mail wurde bereits gesendet. Sie können in :seconds s einen neuen Code anfordern.',
        'resent' => 'Der Code wurde erneut gesendet.',
        'throttle' => 'Zu viele Versuche. Versuchen Sie es in :seconds s erneut.',
        'mail_failed' => 'Der Bestätigungscode konnte nicht gesendet werden: Der E-Mail-Versand ist nicht verfügbar. Prüfen Sie die E-Mail-Einstellungen.',
    ],

    'mail' => [
        'subject' => ':code ist Ihr Anmeldecode für den Admin-Bereich',
        'title' => 'Anmeldecode für den Admin-Bereich',
        'heading' => 'Anmeldung beim Admin-Bereich',
        'code_label' => 'Bestätigungscode:',
        'valid_for' => 'Der Code ist :count Minute gültig.|Der Code ist :count Minuten gültig.',
        'not_you' => 'Falls Sie nicht versucht haben, sich beim Admin-Bereich anzumelden, geben Sie diesen Code an niemanden weiter und ändern Sie Ihr Passwort: Jemand kennt es.',
    ],
];

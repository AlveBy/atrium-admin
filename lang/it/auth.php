<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Queste credenziali non corrispondono ai nostri dati.',
    'throttle' => 'Troppi tentativi di accesso. Riprova tra :seconds s.',
    'deactivated' => 'Il tuo account è disattivato.',

    'login' => [
        'title' => 'Accedi',
        'subtitle' => 'Accedi al pannello di amministrazione',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Ricordami',
        'submit' => 'Accedi',
        'submitting' => 'Accesso in corso…',
    ],

    'validation' => [
        'email_required' => 'L\'email è obbligatoria.',
        'email_invalid' => 'Inserisci un indirizzo email valido.',
        'password_required' => 'La password è obbligatoria.',
    ],

    'two_factor' => [
        'title' => 'Conferma l\'accesso',
        'code_sent_to' => 'Abbiamo inviato un codice a :email',
        'code' => 'Codice ricevuto via email',
        'submit' => 'Conferma',
        'submitting' => 'Verifica in corso…',
        'resend' => 'Invia di nuovo il codice',
        'resend_in' => 'Invia di nuovo tra :seconds s',
        'switch_user' => 'Accedi con un altro utente',
        'code_required' => 'Inserisci il codice ricevuto via email.',
        'code_invalid' => 'Il codice non è corretto o è scaduto. Controlla l\'email o richiedine uno nuovo.',
        'resend_too_soon' => 'L\'email è già stata inviata. Puoi richiederne una nuova tra :seconds s.',
        'resent' => 'Il codice è stato inviato di nuovo.',
        'throttle' => 'Troppi tentativi. Riprova tra :seconds s.',
        'mail_failed' => 'Impossibile inviare il codice di conferma: la posta non è disponibile. Controlla le impostazioni email.',
    ],

    'mail' => [
        'subject' => ':code è il tuo codice di accesso al pannello di amministrazione',
        'title' => 'Codice di accesso al pannello di amministrazione',
        'heading' => 'Accesso al pannello di amministrazione',
        'code_label' => 'Codice di conferma:',
        'valid_for' => 'Il codice è valido per :count minuto.|Il codice è valido per :count minuti.',
        'not_you' => 'Se non hai tentato di accedere al pannello di amministrazione, non condividere questo codice con nessuno e cambia la password: qualcuno la conosce.',
    ],
];

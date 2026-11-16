<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds s.',
    'deactivated' => 'Votre compte est désactivé.',

    'login' => [
        'title' => 'Connexion',
        'subtitle' => 'Connectez-vous à l\'administration',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'remember' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
        'submitting' => 'Connexion…',
    ],

    'validation' => [
        'email_required' => 'L\'adresse e-mail est obligatoire.',
        'email_invalid' => 'Saisissez une adresse e-mail valide.',
        'password_required' => 'Le mot de passe est obligatoire.',
    ],

    'two_factor' => [
        'title' => 'Confirmer la connexion',
        'code_sent_to' => 'Nous avons envoyé un code à :email',
        'code' => 'Code reçu par e-mail',
        'submit' => 'Confirmer',
        'submitting' => 'Vérification…',
        'resend' => 'Renvoyer le code',
        'resend_in' => 'Renvoyer dans :seconds s',
        'switch_user' => 'Se connecter avec un autre compte',
        'code_required' => 'Saisissez le code reçu par e-mail.',
        'code_invalid' => 'Le code est incorrect ou a expiré. Vérifiez l\'e-mail ou demandez-en un nouveau.',
        'resend_too_soon' => 'L\'e-mail a déjà été envoyé. Vous pourrez en demander un nouveau dans :seconds s.',
        'resent' => 'Le code a été renvoyé.',
        'throttle' => 'Trop de tentatives. Réessayez dans :seconds s.',
        'mail_failed' => 'Impossible d\'envoyer le code de confirmation : la messagerie est indisponible. Vérifiez les paramètres de messagerie.',
    ],

    'mail' => [
        'subject' => ':code est votre code de connexion à l\'administration',
        'title' => 'Code de connexion à l\'administration',
        'heading' => 'Connexion à l\'administration',
        'code_label' => 'Code de confirmation :',
        'valid_for' => 'Le code est valable :count minute.|Le code est valable :count minutes.',
        'not_you' => 'Si vous n\'avez pas essayé de vous connecter à l\'administration, ne communiquez ce code à personne et changez votre mot de passe : quelqu\'un le connaît.',
    ],
];

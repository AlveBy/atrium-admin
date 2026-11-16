<?php

declare(strict_types=1);

/*
 * Signing in: the password step, the e-mailed code and the e-mail itself.
 */
return [
    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many sign-in attempts. Try again in :seconds s.',
    'deactivated' => 'Your account is deactivated.',

    'login' => [
        'title' => 'Sign in',
        'subtitle' => 'Sign in to the admin panel',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Remember me',
        'submit' => 'Sign in',
        'submitting' => 'Signing in…',
    ],

    'validation' => [
        'email_required' => 'Email is required.',
        'email_invalid' => 'Enter a valid email.',
        'password_required' => 'Password is required.',
    ],

    'two_factor' => [
        'title' => 'Confirm sign-in',
        'code_sent_to' => 'We sent a code to :email',
        'code' => 'Code from the email',
        'submit' => 'Confirm',
        'submitting' => 'Checking…',
        'resend' => 'Send the code again',
        'resend_in' => 'Send again in :seconds s',
        'switch_user' => 'Sign in as another user',
        'code_required' => 'Enter the code from the email.',
        'code_invalid' => 'The code is wrong or has expired. Check the email or request a new one.',
        'resend_too_soon' => 'The email has already been sent. You can request a new one in :seconds s.',
        'resent' => 'The code has been sent again.',
        'throttle' => 'Too many attempts. Try again in :seconds s.',
        'mail_failed' => 'Could not send the confirmation code: mail is unavailable. Check the mail settings.',
    ],

    'mail' => [
        'subject' => ':code is your admin panel sign-in code',
        'title' => 'Admin panel sign-in code',
        'heading' => 'Signing in to the admin panel',
        'code_label' => 'Confirmation code:',
        'valid_for' => 'The code is valid for :count minute.|The code is valid for :count minutes.',
        'not_you' => 'If you did not try to sign in to the admin panel, do not share this code with anyone and change your password: someone knows it.',
    ],
];

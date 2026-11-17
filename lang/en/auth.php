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
        'or' => 'or',
    ],

    'validation' => [
        'email_required' => 'Email is required.',
        'email_invalid' => 'Enter a valid email.',
        'password_required' => 'Password is required.',
    ],
];

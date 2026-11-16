<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Bu kimlik bilgileri kayıtlarımızla eşleşmiyor.',
    'throttle' => 'Çok fazla giriş denemesi. :seconds sn sonra tekrar deneyin.',
    'deactivated' => 'Hesabınız devre dışı bırakıldı.',

    'login' => [
        'title' => 'Giriş yap',
        'subtitle' => 'Yönetim paneline giriş yapın',
        'email' => 'E-posta',
        'password' => 'Parola',
        'remember' => 'Beni hatırla',
        'submit' => 'Giriş yap',
        'submitting' => 'Giriş yapılıyor…',
    ],

    'validation' => [
        'email_required' => 'E-posta gereklidir.',
        'email_invalid' => 'Geçerli bir e-posta girin.',
        'password_required' => 'Parola gereklidir.',
    ],

    'two_factor' => [
        'title' => 'Girişi onaylayın',
        'code_sent_to' => ':email adresine bir kod gönderdik',
        'code' => 'E-postadaki kod',
        'submit' => 'Onayla',
        'submitting' => 'Kontrol ediliyor…',
        'resend' => 'Kodu yeniden gönder',
        'resend_in' => ':seconds sn sonra yeniden gönder',
        'switch_user' => 'Başka bir kullanıcı olarak giriş yap',
        'code_required' => 'E-postadaki kodu girin.',
        'code_invalid' => 'Kod yanlış veya süresi dolmuş. E-postayı kontrol edin veya yeni bir kod isteyin.',
        'resend_too_soon' => 'E-posta zaten gönderildi. :seconds sn sonra yeni bir kod isteyebilirsiniz.',
        'resent' => 'Kod yeniden gönderildi.',
        'throttle' => 'Çok fazla deneme. :seconds sn sonra tekrar deneyin.',
        'mail_failed' => 'Onay kodu gönderilemedi: e-posta hizmeti kullanılamıyor. E-posta ayarlarını kontrol edin.',
    ],

    'mail' => [
        'subject' => 'Yönetim paneli giriş kodunuz: :code',
        'title' => 'Yönetim paneli giriş kodu',
        'heading' => 'Yönetim paneline giriş',
        'code_label' => 'Onay kodu:',
        'valid_for' => 'Kod :count dakika geçerlidir.',
        'not_you' => 'Yönetim paneline giriş yapmayı siz denemediyseniz bu kodu kimseyle paylaşmayın ve parolanızı değiştirin: başka biri parolanızı biliyor.',
    ],
];

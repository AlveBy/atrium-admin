<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'メールアドレスまたはパスワードが正しくありません。',
    'throttle' => 'ログイン試行回数が多すぎます。:seconds 秒後にもう一度お試しください。',
    'deactivated' => 'このアカウントは無効化されています。',

    'login' => [
        'title' => 'ログイン',
        'subtitle' => '管理パネルにログイン',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'remember' => 'ログイン状態を保持',
        'submit' => 'ログイン',
        'submitting' => 'ログイン中…',
    ],

    'validation' => [
        'email_required' => 'メールアドレスを入力してください。',
        'email_invalid' => '有効なメールアドレスを入力してください。',
        'password_required' => 'パスワードを入力してください。',
    ],

    'two_factor' => [
        'title' => 'ログインの確認',
        'code_sent_to' => ':email に確認コードを送信しました',
        'code' => 'メールに記載のコード',
        'submit' => '確認',
        'submitting' => '確認中…',
        'resend' => 'コードを再送信',
        'resend_in' => ':seconds 秒後に再送信できます',
        'switch_user' => '別のユーザーでログイン',
        'code_required' => 'メールに記載のコードを入力してください。',
        'code_invalid' => 'コードが正しくないか、有効期限が切れています。メールを確認するか、新しいコードをリクエストしてください。',
        'resend_too_soon' => 'メールは既に送信されています。:seconds 秒後に新しいコードをリクエストできます。',
        'resent' => 'コードを再送信しました。',
        'throttle' => '試行回数が多すぎます。:seconds 秒後にもう一度お試しください。',
        'mail_failed' => '確認コードを送信できませんでした。メールを利用できません。メール設定を確認してください。',
    ],

    'mail' => [
        'subject' => '管理パネルのログインコード: :code',
        'title' => '管理パネルのログインコード',
        'heading' => '管理パネルへのログイン',
        'code_label' => '確認コード:',
        'valid_for' => 'このコードの有効期間は :count 分です。',
        'not_you' => '管理パネルへのログインに心当たりがない場合は、このコードを誰にも教えず、パスワードを変更してください。第三者にパスワードを知られています。',
    ],
];

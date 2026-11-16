<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => '用户名或密码错误。',
    'throttle' => '登录尝试次数过多。请在 :seconds 秒后重试。',
    'deactivated' => '您的账户已被停用。',

    'login' => [
        'title' => '登录',
        'subtitle' => '登录管理面板',
        'email' => '电子邮件',
        'password' => '密码',
        'remember' => '记住我',
        'submit' => '登录',
        'submitting' => '正在登录…',
    ],

    'validation' => [
        'email_required' => '请填写电子邮件。',
        'email_invalid' => '请输入有效的电子邮件。',
        'password_required' => '请填写密码。',
    ],

    'two_factor' => [
        'title' => '确认登录',
        'code_sent_to' => '我们已将验证码发送至 :email',
        'code' => '邮件中的验证码',
        'submit' => '确认',
        'submitting' => '正在验证…',
        'resend' => '重新发送验证码',
        'resend_in' => ':seconds 秒后可重新发送',
        'switch_user' => '以其他用户身份登录',
        'code_required' => '请输入邮件中的验证码。',
        'code_invalid' => '验证码错误或已过期。请检查邮件或重新获取验证码。',
        'resend_too_soon' => '邮件已发送。您可以在 :seconds 秒后重新获取。',
        'resent' => '验证码已重新发送。',
        'throttle' => '尝试次数过多。请在 :seconds 秒后重试。',
        'mail_failed' => '无法发送验证码：邮件服务不可用。请检查邮件设置。',
    ],

    'mail' => [
        'subject' => ':code 是您的管理面板登录验证码',
        'title' => '管理面板登录验证码',
        'heading' => '登录管理面板',
        'code_label' => '验证码：',
        'valid_for' => '验证码 :count 分钟内有效。',
        'not_you' => '如果您并未尝试登录管理面板，请勿将此验证码告诉任何人，并立即更改密码：已有他人知道您的密码。',
    ],
];

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('atrium::auth.mail.title') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:#2563eb;color:#fff;padding:18px 24px;border-radius:4px 4px 0 0;">
            <h1 style="margin:0;font-size:18px;">{{ __('atrium::auth.mail.heading') }}</h1>
            <div style="margin-top:4px;font-size:14px;opacity:.9;">{{ config('app.url') }}</div>
        </div>
        <div style="background:#fff;padding:24px;border:1px solid #e5e5e5;border-top:none;border-radius:0 0 4px 4px;">
            <p style="margin:0 0 16px;font-size:14px;">{{ __('atrium::auth.mail.code_label') }}</p>

            <div style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#222;padding:14px 0;">{{ $code }}</div>

            <p style="margin:16px 0 0;font-size:14px;color:#555;">
                {{ trans_choice('atrium::auth.mail.valid_for', $ttlMinutes) }}
            </p>

            <p style="margin:18px 0 0;font-size:13px;color:#757575;">
                {{ __('atrium::auth.mail.not_you') }}
            </p>
        </div>
    </div>
</body>
</html>

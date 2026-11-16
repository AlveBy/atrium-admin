<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Код для входа в админку</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:#c82f12;color:#fff;padding:18px 24px;border-radius:4px 4px 0 0;">
            <h1 style="margin:0;font-size:18px;">Вход в панель управления</h1>
            <div style="margin-top:4px;font-size:14px;opacity:.9;">{{ config('app.url') }}</div>
        </div>
        <div style="background:#fff;padding:24px;border:1px solid #e5e5e5;border-top:none;border-radius:0 0 4px 4px;">
            <p style="margin:0 0 16px;font-size:14px;">Код подтверждения:</p>

            <div style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#222;padding:14px 0;">{{ $code }}</div>

            <p style="margin:16px 0 0;font-size:14px;color:#555;">
                Код действует {{ $ttlMinutes }}
                {{ trans_choice('{1}минуту|[2,4]минуты|[5,*]минут', $ttlMinutes) }}.
            </p>

            <p style="margin:18px 0 0;font-size:13px;color:#999;">
                Если вы не входили в панель управления, код никому не сообщайте
                и смените пароль — кто-то знает его.
            </p>
        </div>
    </div>
</body>
</html>

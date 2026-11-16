<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Vuelve a intentarlo en :seconds s.',
    'deactivated' => 'Tu cuenta está desactivada.',

    'login' => [
        'title' => 'Iniciar sesión',
        'subtitle' => 'Inicia sesión en el panel de administración',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'remember' => 'Recordarme',
        'submit' => 'Iniciar sesión',
        'submitting' => 'Iniciando sesión…',
    ],

    'validation' => [
        'email_required' => 'El correo electrónico es obligatorio.',
        'email_invalid' => 'Introduce un correo electrónico válido.',
        'password_required' => 'La contraseña es obligatoria.',
    ],

    'two_factor' => [
        'title' => 'Confirmar inicio de sesión',
        'code_sent_to' => 'Hemos enviado un código a :email',
        'code' => 'Código del correo',
        'submit' => 'Confirmar',
        'submitting' => 'Comprobando…',
        'resend' => 'Volver a enviar el código',
        'resend_in' => 'Volver a enviar en :seconds s',
        'switch_user' => 'Iniciar sesión con otro usuario',
        'code_required' => 'Introduce el código del correo.',
        'code_invalid' => 'El código no es correcto o ha caducado. Revisa el correo o solicita uno nuevo.',
        'resend_too_soon' => 'El correo ya se ha enviado. Podrás solicitar uno nuevo en :seconds s.',
        'resent' => 'El código se ha vuelto a enviar.',
        'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds s.',
        'mail_failed' => 'No se pudo enviar el código de confirmación: el correo no está disponible. Revisa la configuración del correo.',
    ],

    'mail' => [
        'subject' => ':code es tu código de acceso al panel de administración',
        'title' => 'Código de acceso al panel de administración',
        'heading' => 'Inicio de sesión en el panel de administración',
        'code_label' => 'Código de confirmación:',
        'valid_for' => 'El código es válido durante :count minuto.|El código es válido durante :count minutos.',
        'not_you' => 'Si no has intentado iniciar sesión en el panel de administración, no compartas este código con nadie y cambia tu contraseña: alguien la conoce.',
    ],
];

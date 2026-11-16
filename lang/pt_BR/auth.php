<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Essas credenciais não correspondem aos nossos registros.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds s.',
    'deactivated' => 'Sua conta está desativada.',

    'login' => [
        'title' => 'Entrar',
        'subtitle' => 'Entre no painel de administração',
        'email' => 'E-mail',
        'password' => 'Senha',
        'remember' => 'Lembrar de mim',
        'submit' => 'Entrar',
        'submitting' => 'Entrando…',
    ],

    'validation' => [
        'email_required' => 'O e-mail é obrigatório.',
        'email_invalid' => 'Digite um e-mail válido.',
        'password_required' => 'A senha é obrigatória.',
    ],

    'two_factor' => [
        'title' => 'Confirmar login',
        'code_sent_to' => 'Enviamos um código para :email',
        'code' => 'Código do e-mail',
        'submit' => 'Confirmar',
        'submitting' => 'Verificando…',
        'resend' => 'Reenviar código',
        'resend_in' => 'Reenviar em :seconds s',
        'switch_user' => 'Entrar com outro usuário',
        'code_required' => 'Digite o código do e-mail.',
        'code_invalid' => 'O código está incorreto ou expirou. Verifique o e-mail ou solicite um novo.',
        'resend_too_soon' => 'O e-mail já foi enviado. Você pode solicitar um novo em :seconds s.',
        'resent' => 'O código foi reenviado.',
        'throttle' => 'Muitas tentativas. Tente novamente em :seconds s.',
        'mail_failed' => 'Não foi possível enviar o código de confirmação: o e-mail está indisponível. Verifique as configurações de e-mail.',
    ],

    'mail' => [
        'subject' => ':code é seu código de acesso ao painel de administração',
        'title' => 'Código de acesso ao painel de administração',
        'heading' => 'Acesso ao painel de administração',
        'code_label' => 'Código de confirmação:',
        'valid_for' => 'O código é válido por :count minuto.|O código é válido por :count minutos.',
        'not_you' => 'Se você não tentou entrar no painel de administração, não compartilhe este código com ninguém e altere sua senha: alguém a conhece.',
    ],
];

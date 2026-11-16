<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'failed' => 'Бұл тіркелгі деректері біздің жазбаларға сәйкес келмейді.',
    'throttle' => 'Кіру әрекеттері тым көп. :seconds секундтан кейін қайталап көріңіз.',
    'deactivated' => 'Тіркелгіңіз өшірілген.',

    'login' => [
        'title' => 'Кіру',
        'subtitle' => 'Әкімшілік панеліне кіру',
        'email' => 'Эл. пошта',
        'password' => 'Құпиясөз',
        'remember' => 'Мені есте сақтау',
        'submit' => 'Кіру',
        'submitting' => 'Кіру орындалуда…',
    ],

    'validation' => [
        'email_required' => 'Эл. поштаны енгізу міндетті.',
        'email_invalid' => 'Жарамды эл. пошта енгізіңіз.',
        'password_required' => 'Құпиясөзді енгізу міндетті.',
    ],

    'two_factor' => [
        'title' => 'Кіруді растау',
        'code_sent_to' => 'Кодты :email мекенжайына жібердік',
        'code' => 'Хаттағы код',
        'submit' => 'Растау',
        'submitting' => 'Тексерілуде…',
        'resend' => 'Кодты қайта жіберу',
        'resend_in' => ':seconds секундтан кейін қайта жіберу',
        'switch_user' => 'Басқа пайдаланушы ретінде кіру',
        'code_required' => 'Хаттағы кодты енгізіңіз.',
        'code_invalid' => 'Код қате немесе оның мерзімі өтіп кеткен. Хатты тексеріңіз немесе жаңасын сұраңыз.',
        'resend_too_soon' => 'Хат жіберіліп қойды. Жаңасын :seconds секундтан кейін сұрай аласыз.',
        'resent' => 'Код қайта жіберілді.',
        'throttle' => 'Әрекеттер тым көп. :seconds секундтан кейін қайталап көріңіз.',
        'mail_failed' => 'Растау кодын жіберу мүмкін болмады: пошта қолжетімсіз. Пошта параметрлерін тексеріңіз.',
    ],

    'mail' => [
        'subject' => ':code — әкімшілік панеліне кіру коды',
        'title' => 'Әкімшілік панеліне кіру коды',
        'heading' => 'Әкімшілік панеліне кіру',
        'code_label' => 'Растау коды:',
        'valid_for' => 'Код :count минут бойы жарамды.',
        'not_you' => 'Егер Сіз әкімшілік панеліне кіруге әрекеттенбесеңіз, бұл кодты ешкімге бермеңіз және құпиясөзді өзгертіңіз: оны біреу біледі.',
    ],
];

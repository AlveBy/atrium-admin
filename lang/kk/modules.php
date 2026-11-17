<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The installed module packages and their settings.
 */
return [
    'title' => 'Модульдер',
    'index_hint' => 'Осы сайтқа орнатылған модуль пакеттері. Баптаулары бар модульде оларға апаратын түйме бар.',
    'empty' => 'Модуль пакеттері орнатылмаған.',
    'module' => 'Модуль',
    'package' => 'Пакет',
    'settings' => 'Баптаулар',
    'settings_title' => ':module: баптаулар',
    'per_site' => 'Бұл «:site» сайтының баптаулары. Басқа сайтты баптау үшін оны жоғарғы жолақтан таңдаңыз.',
    'saved' => 'Баптаулар сақталды.',
    'enable' => 'Қосу',
    'disable' => 'Өшіру',
    'enabled' => '«:module» модулі қосылды.',
    'disabled' => '«:module» модулі өшірілді. Деректері мен баптаулары сақталды.',
    'parent_off' => 'Алдымен «:parent» модулін қосыңыз.',
    'config_locked' => 'config/atrium.php файлын өзгерту мүмкін болмады.',
    'required_title' => 'Қосылған, бірақ орнатылмаған',
    'required_hint' => 'composer.json ішіндегі әлі орнатылмаған пакеттер. Орнату:',
    'catalogue_title' => 'Каталог',
    'catalogue_hint' => 'Packagist-те жарияланған Atrium модульдері. Модуль қосу үшін жоба қалтасында орындаңыз:',
    'catalogue_loading' => 'Каталог жүктелуде…',
    'catalogue_unavailable' => 'Packagist жауап бермеді. Кейінірек қайталаңыз.',
    'catalogue_empty' => 'Packagist-те модуль табылмады.',
    'status_installed' => 'Орнатылған',
    'status_disabled' => 'Өшірулі',
    'status_required' => 'Қосылған',
    'downloads' => 'Жүктеулер: :count',
];

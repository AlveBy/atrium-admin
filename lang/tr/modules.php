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
    'title' => 'Modüller',
    'index_hint' => 'Bu sitede kurulu modül paketleri. Ayarları olan bir modülün ayarlara giden bir düğmesi vardır.',
    'empty' => 'Kurulu modül paketi yok.',
    'module' => 'Modül',
    'package' => 'Paket',
    'settings' => 'Ayarlar',
    'settings_title' => ':module: ayarlar',
    'per_site' => 'Bunlar ":site" sitesinin ayarlarıdır. Başka bir sitenin ayarları için üst bölümden o siteyi seçin.',
    'saved' => 'Ayarlar kaydedildi.',
    'enable' => 'Aç',
    'disable' => 'Kapat',
    'enabled' => ':module açıldı.',
    'disabled' => ':module kapatıldı. Verileri ve ayarları korunuyor.',
    'parent_off' => 'Önce :parent modülünü açın.',
    'config_locked' => 'config/atrium.php düzenlenemedi.',
    'required_title' => 'Gerekli, kurulmamış',
    'required_hint' => 'composer.json içinde olup henüz kurulmamış paketler. Kurmak için:',
    'catalogue_title' => 'Katalog',
    'catalogue_hint' => 'Packagist’te yayımlanan Atrium modülleri. Eklemek için proje klasöründe çalıştırın:',
    'catalogue_loading' => 'Katalog yükleniyor…',
    'catalogue_unavailable' => 'Packagist yanıt vermedi. Daha sonra tekrar deneyin.',
    'catalogue_empty' => 'Packagist’te modül bulunamadı.',
    'status_installed' => 'Kurulu',
    'status_disabled' => 'Kapalı',
    'status_required' => 'Gerekli',
    'downloads' => ':count indirme',
];

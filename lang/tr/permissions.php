<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'Süper yönetici',
    'superadmin_hint' => 'Rol olmadan her şeyi yapabilir; rolleri ve diğer süper yöneticileri yalnızca o yönetir.',
    'roles' => 'Roller',
    'roles_hint' => 'Yönetici, işaretli rollerden herhangi birinin izin verdiği her şeyi yapabilir.',
    'site_roles' => 'Tek sitedeki roller',
    'site_roles_hint' => 'Yalnızca panelde o site seçiliyken, yukarıdaki rollere ek olarak geçerlidir: bir siteyi düzenleyip diğerini yalnızca görüntüleyebilirsiniz.',
    'forbidden' => 'Rolünüz buna izin vermiyor.',
    'last_superadmin' => 'Bu son etkin süper yönetici. Rolleri yönetmek için birinin kalması gerekir.',
    'saved' => 'Rol kaydedildi.',
    'deleted' => 'Rol silindi.',
    'create_title' => 'Yeni rol',
    'edit_title' => 'Rol: :name',
    'name' => 'Ad',
    'permissions' => 'İzinler',
    'section' => 'Bölüm',
    'permissions_count' => 'İzinler',
    'users_count' => 'Yöneticiler',
    'select_all' => 'Tümünü seç',
    'clear' => 'Tümünü kaldır',
    'all' => 'Tümü',
    'other' => 'Diğer',
    'matrix_hint' => '“Görüntüle” listeyi ve kayıtları salt okunur açar. Oluşturma, düzenleme ve silme onsuz işe yaramaz, bu yüzden onlarla birlikte işaretlenir.',
    'index_hint' => 'Rol bir izinler kümesidir; roller yöneticilere kendi sayfalarında verilir. Süper yöneticinin role ihtiyacı yoktur: her şeyi yapabilir.',
    'empty' => 'Henüz rol yok.',
    'delete_title' => 'Rol silinsin mi?',
    'delete_confirm' => 'bu role sahip yöneticiler onun izin verdiklerini kaybeder.',

    'actions' => [
        'view' => 'Görüntüle',
        'create' => 'Oluştur',
        'update' => 'Düzenle',
        'delete' => 'Sil',
        'restore' => 'Çöp kutusundan geri yükleme',
        'force_delete' => 'Kalıcı olarak silme',
        'use' => 'Token oluşturma ve kullanma',
        'settings' => 'Ayarlar',
        'import' => 'İçe aktarma',
        'export' => 'Dışa aktarma',
    ],
];

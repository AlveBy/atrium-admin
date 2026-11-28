<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Sayfa düzenleyici: :title',
    'open' => 'Sayfa düzenleyiciyi aç',
    'back' => 'Forma dön',
    'save_draft' => 'Taslağı kaydet',
    'publish' => 'Yayımla',
    'failed' => 'İşlem başarısız oldu. Bağlantıyı kontrol edip tekrar deneyin.',
    'leave' => 'Değişiklikler kaydedilmedi. Düzenleyiciden çıkıp bunlar kaybedilsin mi?',
    'stale' => 'Yayımlanan sayfa bu taslak başladıktan sonra değişti. Yayımlarken bu değişiklikler değiştirilmeden önce onay istenir.',
    'hidden' => 'Bu sayfa kapalı: yayımlamak onu sitede göstermez.',
    'palette' => 'Bloklar',
    'palette_hint' => 'Bir bloğu bir sütuna sürükleyin veya seçili öğeden sonraya koymak için üzerine basın.',
    'add_type' => 'Blok ekle: :type',
    'column_full' => 'Burada bir blok daha için yer yok.',
    'structure' => 'Sayfa yapısı',
    'drop_here' => 'Bloğu buraya sürükleyin',
    'has_error' => 'Hata',
    'not_drawn' => 'Sayfada henüz görünmüyor',
    'settings' => 'Ayarlar',
    'nothing_selected' => 'Ayarlarını değiştirmek için bir bölüm, sütun veya blok seçin.',
    'move_to' => 'Sütuna taşı',
    'move' => 'Taşı',
    'status' => [
        'published' => 'Yayımda',
        'unsaved' => 'Kaydedilmemiş değişiklikler',
        'draft' => 'Taslak :date tarihinde kaydedildi, yayımlanmadı',
        'draft_by' => 'Taslak :date tarihinde :author tarafından kaydedildi, yayımlanmadı',
    ],
    'notices' => [
        'draft' => 'Taslak kaydedildi. Sitede yayımlanan sayfa görünür.',
        'publish' => 'Yayımlandı.',
        'discard' => 'Değişiklikler geri alındı.',
        'version' => 'Sürüm düzenleyicide açık. Sitede görünmesi için yayımlayın.',
    ],
    'discard' => [
        'button' => 'Değişiklikleri geri al',
        'title' => 'Değişiklikler geri alınsın mı?',
        'question' => 'Taslak ve kaydedilmemiş değişiklikler silinir; düzenleyici yayımlanan sayfaya döner.',
    ],
    'conflicts' => [
        'draft' => 'Siz sayfayı açtıktan sonra biri sayfanın taslağını kaydetti veya sildi.',
        'published' => 'Yayımlanan sayfa bu düzenleme başladıktan sonra değişti.',
    ],
    'conflict' => [
        'title' => 'Sayfa başka bir yerde değiştirildi',
        'hint' => 'Yeni sayfayı yüklemek kaydedilmemiş değişikliklerinizi siler. Devam etmek diğer değişiklikleri sizinkilerle değiştirir.',
        'reload' => 'Yeni sayfayı yükle',
        'force' => 'Yine de devam et',
    ],
    'versions' => [
        'button' => 'Sürümler',
        'title' => 'Sayfanın önceki sürümleri',
        'hint' => 'Bir sürümü açmak, yayımlayana kadar sitede hiçbir şeyi değiştirmez.',
        'replaces' => 'Bir sürümü açmak kaydedilmemiş değişikliklerinizin yerini alır.',
        'open' => 'Düzenleyicide aç',
        'current' => 'şu an yayımda',
    ],
    'preview' => [
        'title' => 'Önizleme',
        'updating' => 'Güncelleniyor…',
        'hint' => 'Bir kısmını seçmek için sayfaya tıklayın.',
        'failed' => 'Önizleme çizilemedi.',
        'selected' => 'Seçili: :place',
    ],
    'said' => [
        'added' => 'Eklendi: :place',
        'moved' => 'Taşındı, yeni yeri: :place',
        'removed' => 'Silindi: :place',
    ],
    'count' => [
        'sections' => ':count bölüm',
        'blocks' => ':count blok',
        'empty' => 'Sayfada henüz blok yok.',
    ],
];

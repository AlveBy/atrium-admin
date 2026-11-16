<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Temizle',

    'content' => [
        'mode_text' => 'Metin',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Görsel düzenleyici',
    ],

    'editor' => [
        'placeholder' => 'Yazmaya başlayın…',
        'format' => 'Metin stili',
        'format_paragraph' => 'Normal metin',
        'format_h1' => 'Başlık 1',
        'format_h2' => 'Başlık 2',
        'format_h3' => 'Başlık 3',
        'format_h4' => 'Başlık 4',
        'format_h5' => 'Başlık 5',
        'format_h6' => 'Başlık 6',
        'format_blockquote' => 'Alıntı',
        'font_size' => 'Yazı tipi boyutu',
        'font_size_default' => 'Boyut',
        'font_size_pt' => ':size pt',
        'text_color' => 'Metin rengi',
        'bold' => 'Kalın',
        'bold_shortcut' => 'Kalın (Ctrl+B)',
        'italic' => 'İtalik',
        'italic_shortcut' => 'İtalik (Ctrl+I)',
        'underline' => 'Altı çizili',
        'underline_shortcut' => 'Altı çizili (Ctrl+U)',
        'strike' => 'Üstü çizili',
        'link' => 'Bağlantı',
        'link_prompt' => 'Bağlantı URL\'si:',
        'bullet_list' => 'Madde işaretli liste',
        'ordered_list' => 'Numaralı liste',
        'heading' => 'Başlık',
        'image' => 'Görsel',
        'file' => 'Dosya (PDF, DOC, XLS)',
        'align_left' => 'Sola hizala',
        'align_center' => 'Ortala',
        'align_right' => 'Sağa hizala',
        'align_justify' => 'İki yana yasla',
        'table_insert' => 'Tablo ekle',
        'table_add_row' => 'Satır ekle',
        'table_delete_row' => 'Satırı sil',
        'table_add_column' => 'Sütun ekle',
        'table_delete_column' => 'Sütunu sil',
        'table_merge_split' => 'Hücreleri birleştir veya böl',
        'table_delete' => 'Tabloyu sil',
    ],

    'upload' => [
        'from_library' => 'Kitaplıktan',
        'progress' => 'Yükleniyor… %:percent',
        'too_big' => 'Dosya izin verilen en büyük boyutu (:max KB) aşıyor.',
        'too_large' => 'Dosya çok büyük — sunucu yüklemeyi reddetti.',
        'network_error' => 'Sunucuya ulaşılamadı. Bağlantınızı kontrol edin.',
        'aborted' => 'Yükleme iptal edildi.',
        'no_url' => 'Sunucu, yüklenen dosyanın bağlantısını döndürmedi.',
        'http_error' => 'Dosya yükleme hatası (HTTP :status).',
        'failed' => 'Dosya yüklenemedi.',
        'request_failed' => 'İstek başarısız oldu',
        'session_expired' => 'Oturumunuzun süresi doldu. Sayfayı yenileyip tekrar giriş yapın.',
    ],

    'gallery' => [
        'add_from_library' => 'Kitaplıktan ekle',
        'counter' => ':current / :count görsel',
        'file_too_big' => ':name dosyası :max KB olan en büyük boyutu aşıyor',
        'upload_failed' => ':name dosyası yüklenemedi',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Tümünü temizle',
        'no_options' => 'Kullanılabilir seçenek yok',
    ],

    'multi_select' => [
        'nothing_found' => 'Hiçbir şey bulunamadı',
    ],

    'map' => [
        'latitude' => 'Enlem',
        'longitude' => 'Boylam',
        'open_on_map' => 'Haritada aç',
        'parse_error' => 'Koordinatlar okunamadı. Beklenen biçim: enlem,boylam — örneğin :example',
    ],

    'repeatable' => [
        'item' => 'Öğe',
        'item_title' => ':label #:number',
        'empty' => 'Öğe yok. Eklemek için “:add” düğmesine tıklayın.',
        'move_up' => 'Yukarı taşı',
        'move_down' => 'Aşağı taşı',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Addan oluştur',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Ara…',
        'top_level' => 'Üst düzey',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Ad',
        'code' => 'Kod',
        'active' => 'Aktif',
        'active_from' => 'Başlangıç tarihi',
        'active_to' => 'Bitiş tarihi',
        'sort' => 'Sıralama',
        'section' => 'Bölüm',
        'parent_section' => 'Üst bölüm',
        'preview_picture' => 'Önizleme görseli',
        'preview_text' => 'Önizleme metni',
        'detail_picture' => 'Ayrıntı görseli',
        'detail_text' => 'Ayrıntılı metin',
        'description' => 'Açıklama',
        'picture' => 'Görsel',
        'created_at' => 'Oluşturuldu',
        'updated_at' => 'Değiştirildi',
    ],

    'tabs' => [
        'label' => 'Sekmeler',
        'main' => 'Genel',
        'section' => 'Bölüm',
        'seo' => 'SEO',
        'additional' => 'Ek',
        'custom_fields' => 'Özel alanlar',
        'element_properties' => 'Öğe özellikleri',
        'preview' => 'Önizleme',
        'detail' => 'Ayrıntılar',
        'sections' => 'Bölümler',
        'description' => 'Açıklama',
    ],

    'validation' => [
        'own_ancestor' => 'Bir bölüm kendi içine veya kendi alt bölümüne yerleştirilemez.',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Import of a module's records from CSV and Excel files.
 */
return [
    'title' => 'İçe aktarma',

    'index' => [
        'upload' => 'Dosya yükle',
        'hint' => 'CSV veya Excel (xlsx), en fazla :size MB. İlk satırda sütun adları bulunur. Kodlama (UTF-8 veya windows-1251) ve ayırıcı (noktalı virgül veya virgül) kendiliğinden tanınır. Bu listeden dışa aktarılan dosya olduğu gibi geri içe aktarılabilir.',
        'choose' => 'İçe aktarılacak dosya',
        'submit' => 'Yükle',
        'history' => 'Önceki içe aktarmalar',
        'empty' => 'Henüz hiçbir şey içe aktarılmadı.',
        'file' => 'Dosya',
        'status' => 'Durum',
        'result' => 'Sonuç',
        'counts' => 'oluşturulan :created, güncellenen :updated, hata :failed',
        'user' => 'Kim',
        'console' => 'konsol',
        'date' => 'Ne zaman',
    ],

    'upload' => [
        'file' => 'dosya',
    ],

    'statuses' => [
        'new' => 'Kontrol bekliyor',
        'checking' => 'Kontrol ediliyor',
        'checked' => 'Kontrol edildi',
        'importing' => 'İçe aktarılıyor',
        'done' => 'Tamamlandı',
        'failed' => 'Başarısız',
        'cancelled' => 'Durduruldu',
    ],

    'delimiters' => [
        ';' => 'noktalı virgül',
        ',' => 'virgül',
        "\t" => 'sekme',
    ],

    'show' => [
        'back' => 'Tüm içe aktarmalar',
        'file' => ':format, :encoding, ayırıcı: :delimiter, :rows satır',
        'workbook' => ':format, :rows satır',
        'checking' => 'Dosya kontrol ediliyor…',
        'importing' => 'İçe aktarılıyor…',
        'progress' => ':processed / :total satır',
        'cancel' => 'Durdur',
        'cancel_confirm' => 'Durdurulsun mu? İçe aktarılanlar kalır.',
        'report' => 'Rapor',
        'check_report' => 'Kontrol',
        'checked' => 'Kontrol bitti: henüz hiçbir şey yazılmadı. İçe aktarma şunları yapacak.',
        'done' => 'İçe aktarma bitti.',
        'cancelled' => 'İçe aktarma durduruldu. Daha önce içe aktarılanlar kaldı.',
        'counts' => [
            'created' => 'Oluşturulan',
            'updated' => 'Güncellenen',
            'skipped' => 'Atlanan',
            'failed' => 'Hata',
            'nodes' => 'Oluşturulan ağaç öğeleri',
        ],
        'will' => [
            'created' => 'Oluşturulacak',
            'updated' => 'Güncellenecek',
            'skipped' => 'Atlanacak',
            'failed' => 'Hata',
            'nodes' => 'Oluşturulacak ağaç öğeleri',
        ],
        'row' => 'Satır',
        'outcome' => 'Sonuç',
        'reason' => 'Neden',
        'skipped_row' => 'atlandı',
        'failed_row' => 'hata',
        'unlisted' => 'Ayrıca :count satır sayıldı ama listede gösterilmedi.',
        'run' => 'İçe aktar',
        'run_hint' => 'Hatalı satırlar dışarıda bırakılır; gerisi kontrolün gösterdiği gibi içe aktarılır.',
        'mapping' => 'Sütunlar',
        'mapping_hint' => 'Her sütunun hangi alanı doldurduğu, sütun adlarına bakılarak tahmin edildi. Boş hücre alanı olduğu gibi bırakır.',
        'column' => 'Sütun',
        'unnamed' => 'Sütun :number',
        'samples' => 'İlk satırlarda',
        'target' => 'Alan',
        'skip' => '— içe aktarma —',
        'options' => 'Nasıl içe aktarılacak',
        'key_hint' => 'Aynı değere sahip kayıt güncellenir; olmayan kayıt oluşturulur.',
        'separator_hint' => 'Bir yolda üst öğeyi alt öğeden ayırır: “Giyim / Ceketler”.',
        'check' => 'Kontrol et',
        'check_again' => 'Yeniden kontrol et',
        'check_hint' => 'Kontrol tüm dosyayı okur ve hiçbir şey yazmaz: kaç kaydın oluşturulacağını ve güncelleneceğini, hangi satırlarda hata olduğunu göreceksiniz.',
    ],

    'settings' => [
        'mapping' => 'Sütunlar',
        'key' => 'Kayıtlar şuna göre bulunsun',
        'mode' => 'Ne yapılsın',
        'separator' => 'Yol ayırıcısı',
    ],

    'modes' => [
        'both' => 'Yeni kayıtları oluştur, mevcut olanları güncelle',
        'create' => 'Yalnızca yeni kayıtları oluştur',
        'update' => 'Yalnızca mevcut kayıtları güncelle',
    ],

    'validation' => [
        'twice' => 'Bir alanı yalnızca tek sütun doldurur; birden fazla kez seçilen: :fields.',
        'key' => 'Kayıtlar “:field” alanına göre bulunuyor, ancak hiçbir sütun bu alanı doldurmuyor.',
    ],

    'failures' => [
        'empty' => 'Dosyada satır yok.',
        'format' => 'Bu ne CSV ne de Excel (xlsx). Eski bir .xls dosyası Excel ile .xlsx veya CSV olarak yeniden kaydedilebilir.',
        'unreadable' => 'Excel dosyası okunamıyor: bozuk veya parola korumalı olabilir.',
        'crashed' => 'İçe aktarma, sunucudaki bir hata yüzünden durdu. Hata günlüğe yazıldı; içe aktarılanlar kaldı.',
        'missing' => ':file dosyası yok.',
        'download' => 'Dosya :url adresinden indirilemedi.',
        'column' => '--map için böyle bir sütun yok: “:column”. Şöyle yazın: “Sütun=alan”.',
        'option' => 'Modülde --option için “:option” diye bir seçenek yok.',
        'module' => '“:module” modülü artık yok.',
        'key' => 'Kayıtlar “:key” alanına göre bulunamaz: modülde böyle bir alan yok.',
    ],

    'problems' => [
        'no_key' => '“:field” boş: kayıt bulunamaz ve oluşturulamaz.',
        'bad_id' => '“:value” bir kimlik değil: sıfırdan büyük bir tam sayı olmalı.',
        'exists' => '“:key” kaydı zaten var, yalnızca yeni kayıtlar oluşturuluyor.',
        'missing' => '“:key” kaydı yok, yalnızca mevcut kayıtlar güncelleniyor.',
        'database' => 'Veritabanı satırı kabul etmedi. Hata günlüğe yazıldı.',
        'not_bool' => '“:field”: “:value” ne evet ne hayır.',
        'not_option' => '“:field”: “:value” diye bir seçenek yok.',
        'ambiguous_option' => '“:field”: birden fazla seçeneğin adı “:value”.',
        'tree_missing' => '“:field”: “:value” diye bir öğe yok.',
        'tree_ambiguous' => '“:field”: birden fazla öğenin adı “:value”: yolunu yazın, “Üst öğe / :value”.',
        'image_url' => 'Görsel adresi geçerli değil: :url',
        'image_path' => 'Görsel yoluna izin verilmiyor: :path',
        'image_type' => 'Bu bir görsel değil (JPEG, PNG, GIF, WebP veya SVG): :path',
        'image_missing' => 'Sitede :path görseli yok.',
        'image_host' => 'Görsel ağ içindeki bir adreste duruyor, bunlar indirilmez: :url',
        'image_download' => 'Görsel indirilemedi: :url',
        'image_large' => 'Görsel :max KB değerinden büyük: :url',
        'image_demo' => 'Demoda görseller adresten indirilmez: :url',
    ],

    'console' => [
        'module' => 'İçe aktarma kabul eden “:module” modülü yok. Kabul edenler: :modules.',
        'source' => 'Dosyayı veya adresini belirtin.',
        'like' => 'Bu modül için :id numaralı içe aktarma yok.',
        'report' => 'Satır: :processed. Oluşturulan: :created, güncellenen: :updated, atlanan: :skipped, hata: :failed, oluşturulan ağaç öğesi: :nodes.',
        'row' => 'Satır :row: :messages',
    ],
];

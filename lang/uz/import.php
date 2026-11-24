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
    'title' => 'Import',

    'index' => [
        'upload' => 'Fayl yuklash',
        'hint' => 'CSV yoki Excel (xlsx), :size MB gacha. Birinchi qatorda ustun nomlari turadi. Kodlash (UTF-8 yoki windows-1251) va ajratgich (nuqtali vergul yoki vergul) oʻzi aniqlanadi. Shu roʻyxatdan eksport qilingan faylni oʻzgartirmasdan qayta import qilish mumkin.',
        'choose' => 'Import qilinadigan fayl',
        'submit' => 'Yuklash',
        'history' => 'Oldingi importlar',
        'empty' => 'Hozircha hech narsa import qilinmagan.',
        'file' => 'Fayl',
        'status' => 'Holati',
        'result' => 'Natija',
        'counts' => 'yaratildi :created, yangilandi :updated, xatolar :failed',
        'user' => 'Kim',
        'console' => 'konsol',
        'date' => 'Qachon',
    ],

    'upload' => [
        'file' => 'fayl',
    ],

    'statuses' => [
        'new' => 'Tekshiruvni kutmoqda',
        'checking' => 'Tekshirilmoqda',
        'checked' => 'Tekshirildi',
        'importing' => 'Import qilinmoqda',
        'done' => 'Tayyor',
        'failed' => 'Xatolik',
        'cancelled' => 'Toʻxtatildi',
    ],

    'delimiters' => [
        ';' => 'nuqtali vergul',
        ',' => 'vergul',
        "\t" => 'tabulyatsiya',
    ],

    'show' => [
        'back' => 'Barcha importlar',
        'file' => ':format, :encoding, ajratgich: :delimiter, qatorlar: :rows',
        'workbook' => ':format, qatorlar: :rows',
        'checking' => 'Fayl tekshirilmoqda…',
        'importing' => 'Import qilinmoqda…',
        'progress' => 'Qatorlar: :processed / :total',
        'cancel' => 'Toʻxtatish',
        'cancel_confirm' => 'Toʻxtatilsinmi? Import qilingani qoladi.',
        'report' => 'Hisobot',
        'check_report' => 'Tekshiruv',
        'checked' => 'Tekshiruv tugadi, bazaga hozircha hech narsa yozilmadi. Import shularni bajaradi.',
        'done' => 'Import tugadi.',
        'cancelled' => 'Import toʻxtatildi. Import qilingani qoldi.',
        'counts' => [
            'created' => 'Yaratildi',
            'updated' => 'Yangilandi',
            'skipped' => 'Oʻtkazib yuborildi',
            'failed' => 'Xatolar',
            'nodes' => 'Yaratilgan daraxt elementlari',
        ],
        'will' => [
            'created' => 'Yaratiladi',
            'updated' => 'Yangilanadi',
            'skipped' => 'Oʻtkazib yuboriladi',
            'failed' => 'Xatolar',
            'nodes' => 'Yaratiladigan daraxt elementlari',
        ],
        'row' => 'Qator',
        'outcome' => 'Natijasi',
        'reason' => 'Sababi',
        'skipped_row' => 'oʻtkazib yuborildi',
        'failed_row' => 'xato',
        'unlisted' => 'Yana :count ta qator hisoblandi, lekin roʻyxatga kiritilmadi.',
        'run' => 'Import qilish',
        'run_hint' => 'Xatoli qatorlar import qilinmaydi, qolgani tekshiruv koʻrsatganidek import qilinadi.',
        'mapping' => 'Ustunlar',
        'mapping_hint' => 'Har bir ustun qaysi maydonni toʻldiradi — ustun nomlariga qarab taxmin qilingan. Boʻsh katak maydonni oʻzgarishsiz qoldiradi.',
        'column' => 'Ustun',
        'unnamed' => 'Ustun :number',
        'samples' => 'Birinchi qatorlarda',
        'target' => 'Maydon',
        'skip' => '— import qilinmasin —',
        'options' => 'Qanday import qilish',
        'key_hint' => 'Qiymati bir xil yozuv yangilanadi, yoʻq yozuv yaratiladi.',
        'separator_hint' => 'Yoʻlda ota elementni uning ichki elementidan ajratadi: “Kiyim / Kurtkalar”.',
        'check' => 'Tekshirish',
        'check_again' => 'Qayta tekshirish',
        'check_hint' => 'Tekshiruv butun faylni oʻqiydi va hech narsa yozmaydi: nechta yozuv yaratilishi va yangilanishi hamda qaysi qatorlarda xato borligi koʻrinadi.',
    ],

    'settings' => [
        'mapping' => 'Ustunlar',
        'key' => 'Yozuvlarni qidirish maydoni',
        'mode' => 'Nima qilish kerak',
        'separator' => 'Yoʻl ajratgichi',
    ],

    'modes' => [
        'both' => 'Yangi yozuvlarni yaratish va mavjudlarini yangilash',
        'create' => 'Faqat yangi yozuvlarni yaratish',
        'update' => 'Faqat mavjud yozuvlarni yangilash',
    ],

    'validation' => [
        'twice' => 'Maydonni faqat bitta ustun toʻldiradi, lekin bir necha marta tanlangan: :fields.',
        'key' => 'Yozuvlar “:field” maydoni boʻyicha qidiriladi, lekin uni birorta ustun toʻldirmaydi.',
    ],

    'failures' => [
        'empty' => 'Faylda qatorlar yoʻq.',
        'format' => 'Bu CSV ham, Excel (xlsx) ham emas. Eski .xls faylni Excel orqali .xlsx yoki CSV sifatida qayta saqlash mumkin.',
        'unreadable' => 'Excel faylini oʻqib boʻlmadi: u shikastlangan yoki parol bilan himoyalangan boʻlishi mumkin.',
        'crashed' => 'Serverdagi xato tufayli import toʻxtadi. U jurnalga yozildi; import qilingani qoldi.',
        'missing' => ':file fayli yoʻq.',
        'download' => 'Faylni :url manzilidan yuklab boʻlmadi.',
        'column' => '--map uchun bunday ustun yoʻq: “:column”. Mana bunday yozing: “Ustun=maydon”.',
        'option' => 'Modulda --option uchun “:option” parametri yoʻq.',
        'module' => '“:module” moduli endi yoʻq.',
        'key' => 'Yozuvlarni “:key” boʻyicha qidirib boʻlmaydi: modulda bunday maydon yoʻq.',
    ],

    'problems' => [
        'no_key' => '“:field” maydoni boʻsh — yozuvni topib ham, yaratib ham boʻlmaydi.',
        'bad_id' => '“:value” identifikator emas: noldan katta butun son boʻlishi kerak.',
        'exists' => '“:key” yozuvi allaqachon bor, faqat yangilari yaratiladi.',
        'missing' => '“:key” yozuvi yoʻq, faqat mavjudlari yangilanadi.',
        'database' => 'Maʼlumotlar bazasi qatorni qabul qilmadi. Xato jurnalga yozildi.',
        'not_bool' => '“:field”: “:value” — “ha” ham, “yoʻq” ham emas.',
        'not_option' => '“:field”: “:value” degan variant yoʻq.',
        'ambiguous_option' => '“:field”: “:value” nomli variant bittadan koʻp.',
        'tree_missing' => '“:field”: “:value” yoʻq.',
        'tree_ambiguous' => '“:field”: “:value” nomlisi bittadan koʻp — yoʻlini yozing: “Asosiy / :value”.',
        'image_url' => 'Rasm manzili notoʻgʻri: :url',
        'image_path' => 'Rasm yoʻliga ruxsat yoʻq: :path',
        'image_type' => 'Bu rasm emas (JPEG, PNG, GIF, WebP yoki SVG): :path',
        'image_missing' => 'Saytda :path rasmi yoʻq.',
        'image_host' => 'Rasm tarmoq ichidagi manzilda turibdi, bunday rasmlar yuklanmaydi: :url',
        'image_download' => 'Rasmni yuklab boʻlmadi: :url',
        'image_large' => 'Rasm :max KB dan katta: :url',
        'image_demo' => 'Demoda rasmlar manzil boʻyicha yuklab olinmaydi: :url',
    ],

    'console' => [
        'module' => 'Importni qabul qiladigan “:module” moduli yoʻq. Qabul qiladiganlari: :modules.',
        'source' => 'Faylni yoki uning manzilini koʻrsating.',
        'like' => 'Bu modul uchun :id raqamli import yoʻq.',
        'report' => 'Qatorlar: :processed. Yaratildi: :created, yangilandi: :updated, oʻtkazib yuborildi: :skipped, xatolar: :failed, yaratilgan daraxt elementlari: :nodes.',
        'row' => 'Qator :row: :messages',
    ],
];

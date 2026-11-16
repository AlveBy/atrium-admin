<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Sayt qoʻshish',
    'tabs' => [
        'site' => 'Sayt',
        'codes' => 'Hisoblagichlar va kodlar',
    ],
    'add_domain' => 'Domen qoʻshish',
    'fields' => [
        'name' => 'Nomi',
        'code' => 'Kod',
        'domains' => 'Domenlar',
        'folder' => 'Papka',
        'languages' => 'Tillar',
        'default_language' => 'Standart til',
        'active' => 'Faol',
        'is_default' => 'Asosiy sayt',
        'sort' => 'Saralash',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex Metrika hisoblagichi',
        'yandex_webvisor' => 'Webvisor',
        'counters_skip_admins' => 'Administratorlarni hisobga olmaslik',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => '<head> ichidagi kod',
        'body_start_code' => '<body> boshidagi kod',
        'body_end_code' => '</body> oldidagi kod',
    ],
    'hints' => [
        'code' => 'Kichik lotin harflari, raqamlar, defislar: main, shop, de. Keyin oʻzgartirib boʻlmaydi.',
        'domains' => 'Sayt shu domenlarda ochiladi: example.com, www.example.com. Birinchisi — asosiysi.',
        'folder' => 'Bitta domendagi bir nechta sayt uchun: site2 bu saytni example.com/site2/ manzilida ochadi. Boʻsh — sayt oʻz domenlarining ildizida. Domeni yoʻq sayt istalgan domenda shu papkada boʻladi.',
        'languages' => 'Ushbu saytning kontent tillari. Hech narsa tanlanmasa — barcha faol tillar.',
        'default_language' => 'Bu til sahifalari manzilda til prefiksisiz ochiladi. Boʻsh qoldirilsa — butun panelning standart tili.',
        'is_default' => 'Boshqa sayt egallamagan har qanday domenda va localhostda ochiladi.',
        'google_tag_id' => 'Google Analytics 4 uchun G-XXXXXXXXXX yoki Tag Manager uchun GTM-XXXXXXX. Faqat identifikator: hisoblagich kodi barcha sahifalarga oʻzi qoʻshiladi.',
        'yandex_metrika_id' => 'Faqat Metrika sozlamalaridagi hisoblagich raqami: kod barcha sahifalarga oʻzi qoʻshiladi.',
        'counters_skip_admins' => 'Tizimga kirgan administratorlarga sahifalar Google Tag va Yandex Metrikasiz beriladi, ularning tashriflari statistikani buzmasligi uchun. Tasdiqlash meta-teglari va quyidagi kod doim chiqariladi.',
        'verification' => ':name meta-tegidagi kod. Tegni butunlay qoʻyish mumkin.',
        'snippet' => 'Ushbu saytning barcha sahifalariga oʻz holicha qoʻyiladi: chat vidjeti, piksel, boshqa har qanday hisoblagich.',
        'robots_txt' => 'Boʻsh qoldirilsa — robotlarga hammasi mumkin. Agar yoʻq boʻlsa, ushbu saytning sitemap.xml manzili bilan “Sitemap:” qatori oʻzi qoʻshiladi.',
        'robots_not_production' => 'Bu server ishchi emas (APP_ENV=:env), shuning uchun bu yerda nima yozilganidan qatʼi nazar, robots.txt indekslashni taqiqlaydi.',
        'robots_file_exists' => 'public/robots.txt fayli mavjud: veb-server shu matn oʻrniga uni qaytaradi. Faylni oʻchiring.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” domen nomi emas.',
        'domain_taken' => ':domain domenidan “:site” sayti allaqachon foydalanmoqda.',
        'folder_invalid' => '“:folder” papka emas: lotin harflari, raqamlar, chiziqcha va pastki chiziq, qismlar qiya chiziq bilan ajratiladi.',
        'folder_reserved' => ':folder papkasi panelning oʻz manzili.',
        'folder_default' => 'Asosiy sayt boshqa sayt egallamagan har qanday manzilga javob beradi, shuning uchun ildizda turadi. Papkani tozalang yoki boshqa saytni asosiy qiling.',
        'folder_taken' => ':address manzili allaqachon “:site” sayti tomonidan ishlatilmoqda.',
    ],
];

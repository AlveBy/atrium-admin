<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Site ekle',
    'tabs' => [
        'site' => 'Site',
        'codes' => 'Sayaçlar ve kodlar',
    ],
    'add_domain' => 'Alan adı ekle',
    'fields' => [
        'name' => 'Ad',
        'code' => 'Kod',
        'domains' => 'Alan adları',
        'folder' => 'Klasör',
        'languages' => 'Diller',
        'default_language' => 'Varsayılan dil',
        'active' => 'Etkin',
        'is_default' => 'Varsayılan site',
        'sort' => 'Sıralama',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex Metrica sayacı',
        'yandex_webvisor' => 'Oturum yeniden oynatma (Webvisor)',
        'counters_skip_admins' => 'Yöneticileri sayma',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => '<head> içindeki kod',
        'body_start_code' => '<body> başındaki kod',
        'body_end_code' => '</body> öncesindeki kod',
    ],
    'hints' => [
        'code' => 'Küçük Latin harfleri, rakamlar, tireler: main, shop, de. Daha sonra değiştirilemez.',
        'domains' => 'Site bu alan adlarında açılır: example.com, www.example.com. İlki ana alan adıdır.',
        'folder' => 'Bir alan adında birden çok site için: site2 bu siteyi example.com/site2/ adresinde açar. Boş — site, alan adlarının kökündedir. Alan adı olmayan bir site her alan adında bu klasördedir.',
        'languages' => 'Bu sitenin içerik dilleri. Hiçbiri seçilmezse — tüm etkin diller.',
        'default_language' => 'Bu dilin sayfalarının adresinde dil öneki olmaz. Boşsa — tüm panelin varsayılan dili.',
        'is_default' => 'Başka bir sitenin sahiplenmediği her alan adında ve localhost\'ta açılır.',
        'google_tag_id' => 'Google Analytics 4 için G-XXXXXXXXXX veya Tag Manager için GTM-XXXXXXX. Yalnızca kimlik: sayaç kodu her sayfaya kendiliğinden eklenir.',
        'yandex_metrika_id' => 'Yalnızca Metrica ayarlarındaki sayaç numarası: kod her sayfaya kendiliğinden eklenir.',
        'counters_skip_admins' => 'Oturum açmış yöneticilere sayfalar Google Tag ve Yandex Metrica olmadan verilir; böylece kendi ziyaretleri istatistikleri bozmaz. Doğrulama etiketleri ve aşağıdaki kod her zaman bulunur.',
        'verification' => ':name meta etiketindeki kod. Etiketin tamamını yapıştırabilirsiniz.',
        'snippet' => 'Bu sitenin her sayfasına olduğu gibi eklenir: sohbet widget\'ı, piksel, başka herhangi bir sayaç.',
        'robots_txt' => 'Boşsa — robotlar her şeyi dizinleyebilir. Yoksa, bu sitenin sitemap.xml adresini içeren bir “Sitemap:” satırı eklenir.',
        'robots_not_production' => 'Bu sunucu canlı ortam değil (APP_ENV=:env), bu yüzden buraya ne yazılırsa yazılsın robots.txt dizinlemeyi yasaklar.',
        'robots_file_exists' => 'public/robots.txt dosyası var: web sunucusu bu metin yerine onu döndürür. Dosyayı silin.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” bir alan adı değil.',
        'domain_taken' => ':domain alan adı “:site” sitesi tarafından zaten kullanılıyor.',
        'folder_invalid' => '“:folder” bir klasör değil: Latin harfleri, rakamlar, tire ve alt çizgiler, parçalar eğik çizgiyle ayrılır.',
        'folder_reserved' => ':folder klasörü panelin kendi adresidir.',
        'folder_default' => 'Varsayılan site, başka bir sitenin sahiplenmediği her adrese yanıt verir, bu yüzden kökte kalır. Klasörü boşaltın veya başka bir siteyi varsayılan yapın.',
        'folder_taken' => ':address adresi zaten “:site” sitesi tarafından kullanılıyor.',
    ],
];

<?php

declare(strict_types=1);

return [
    'create' => 'Add site',
    'tabs' => [
        'site' => 'Site',
        'codes' => 'Counters and codes',
    ],
    'add_domain' => 'Add domain',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'domains' => 'Domains',
        'languages' => 'Languages',
        'default_language' => 'Default language',
        'active' => 'Active',
        'is_default' => 'Default site',
        'sort' => 'Sort order',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex Metrica counter',
        'yandex_webvisor' => 'Session Replay (Webvisor)',
        'counters_skip_admins' => 'Do not count administrators',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Code in <head>',
        'body_start_code' => 'Code at the start of <body>',
        'body_end_code' => 'Code before </body>',
    ],
    'hints' => [
        'code' => 'Lowercase Latin letters, digits, dashes: main, shop, de. Cannot be changed later.',
        'domains' => 'The site opens on these domains: example.com, www.example.com. The first one is the main one.',
        'languages' => 'The content languages of this site. None selected — all active languages.',
        'default_language' => 'Its pages have no language prefix in the address. Empty — the panel-wide default language.',
        'is_default' => 'Opens on any domain no other site claims, and on localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX for Google Analytics 4 or GTM-XXXXXXX for Tag Manager. Only the ID: the counter code is added to every page by itself.',
        'yandex_metrika_id' => 'Only the counter number from the Metrica settings: the code is added to every page by itself.',
        'counters_skip_admins' => 'Signed-in administrators get pages without Google Tag and Yandex Metrica, so their own visits do not spoil the statistics. Verification tags and the code below are always there.',
        'verification' => 'The code from the :name meta tag. You can paste the whole tag.',
        'snippet' => 'Added as is to every page of this site: a chat widget, a pixel, any other counter.',
        'robots_txt' => 'Empty — robots may index everything. A "Sitemap:" line with this site\'s sitemap.xml is added if there is none.',
        'robots_not_production' => 'This server is not production (APP_ENV=:env), so robots.txt forbids indexing whatever is written here.',
        'robots_file_exists' => 'The file public/robots.txt exists: the web server returns it instead of this text. Delete the file.',
    ],
    'errors' => [
        'domain_invalid' => '":domain" is not a domain name.',
        'domain_taken' => 'The domain :domain is already used by the site ":site".',
    ],
];

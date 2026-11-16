<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'サイトを追加',
    'tabs' => [
        'site' => 'サイト',
        'codes' => 'カウンターとコード',
    ],
    'add_domain' => 'ドメインを追加',
    'fields' => [
        'name' => '名前',
        'code' => 'コード',
        'domains' => 'ドメイン',
        'folder' => 'フォルダ',
        'languages' => '言語',
        'default_language' => 'デフォルト言語',
        'active' => '有効',
        'is_default' => 'デフォルトサイト',
        'sort' => '並び順',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex Metrica カウンター',
        'yandex_webvisor' => 'セッションリプレイ（Webvisor）',
        'counters_skip_admins' => '管理者をカウントしない',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => '<head> 内のコード',
        'body_start_code' => '<body> の先頭のコード',
        'body_end_code' => '</body> の直前のコード',
    ],
    'hints' => [
        'code' => '小文字の英字、数字、ハイフン。例: main, shop, de。後から変更できません。',
        'domains' => 'サイトはこれらのドメインで開きます。例: example.com, www.example.com。最初のドメインがメインです。',
        'folder' => '1つのドメインに複数のサイトを置く場合：site2 と入力すると、このサイトは example.com/site2/ で開きます。空の場合、サイトはドメインのルートにあります。ドメインのないサイトは、どのドメインでもこのフォルダにあります。',
        'languages' => 'このサイトのコンテンツ言語です。何も選択しない場合はすべての有効な言語が対象です。',
        'default_language' => 'この言語のページは URL に言語の接頭辞が付きません。空の場合はパネル全体のデフォルト言語になります。',
        'is_default' => '他のサイトが使用していないすべてのドメインと localhost で開きます。',
        'google_tag_id' => 'Google Analytics 4 は G-XXXXXXXXXX、Tag Manager は GTM-XXXXXXX。ID のみを入力してください。カウンターのコードはすべてのページに自動で追加されます。',
        'yandex_metrika_id' => 'Metrica の設定にあるカウンター番号のみを入力してください。コードはすべてのページに自動で追加されます。',
        'counters_skip_admins' => 'ログイン中の管理者には Google Tag と Yandex Metrica を含まないページが返されるため、自分のアクセスが統計に影響しません。確認用タグと下のコードは常に出力されます。',
        'verification' => ':name メタタグのコードです。タグ全体を貼り付けることもできます。',
        'snippet' => 'このサイトのすべてのページにそのまま追加されます。チャットウィジェット、ピクセル、その他のカウンターなど。',
        'robots_txt' => '空の場合、ロボットはすべてをインデックスできます。「Sitemap:」行がなければ、このサイトの sitemap.xml を指す行が自動で追加されます。',
        'robots_not_production' => 'このサーバーは本番環境ではない（APP_ENV=:env）ため、ここに何を書いても robots.txt はインデックスを禁止します。',
        'robots_file_exists' => 'public/robots.txt ファイルが存在します。Web サーバーはこのテキストの代わりにそのファイルを返します。ファイルを削除してください。',
    ],
    'errors' => [
        'domain_invalid' => '「:domain」はドメイン名ではありません。',
        'domain_taken' => 'ドメイン :domain はすでにサイト「:site」で使用されています。',
        'folder_invalid' => '「:folder」はフォルダではありません：ラテン文字、数字、ハイフン、アンダースコアのみ、区切りはスラッシュです。',
        'folder_reserved' => 'フォルダ :folder はパネル自体のアドレスです。',
        'folder_default' => 'デフォルトサイトは他のサイトが使っていないすべてのアドレスに応答するため、ルートに置かれます。フォルダを空にするか、別のサイトをデフォルトにしてください。',
        'folder_taken' => 'アドレス :address はすでにサイト「:site」が使用しています。',
    ],
];

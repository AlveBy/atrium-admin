<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'サイトを追加',
    'add_domain' => 'ドメインを追加',
    'fields' => [
        'name' => '名前',
        'code' => 'コード',
        'domains' => 'ドメイン',
        'languages' => '言語',
        'default_language' => 'デフォルト言語',
        'active' => '有効',
        'is_default' => 'デフォルトサイト',
        'sort' => '並び順',
    ],
    'hints' => [
        'code' => '小文字の英字、数字、ハイフン。例: main, shop, de。後から変更できません。',
        'domains' => 'サイトはこれらのドメインで開きます。例: example.com, www.example.com。最初のドメインがメインです。',
        'languages' => 'このサイトのコンテンツ言語です。何も選択しない場合はすべての有効な言語が対象です。',
        'default_language' => 'この言語のページは URL に言語の接頭辞が付きません。空の場合はパネル全体のデフォルト言語になります。',
        'is_default' => '他のサイトが使用していないすべてのドメインと localhost で開きます。',
    ],
    'errors' => [
        'domain_invalid' => '「:domain」はドメイン名ではありません。',
        'domain_taken' => 'ドメイン :domain はすでにサイト「:site」で使用されています。',
    ],
];

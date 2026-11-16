<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => '言語を追加',
    'fields' => [
        'name' => '名前',
        'code' => 'コード',
        'active' => '有効',
        'is_default' => 'デフォルト',
        'sort' => '並び順',
    ],
    'hints' => [
        'name' => '言語切り替えで訪問者に表示される名前です。例: English, Deutsch, Español',
        'code' => '小文字の英字 2〜3 文字。地域を付けることもできます。例: en, de, pt-br。URL の先頭（/de/…）になり、後から変更できません。',
        'code_fixed' => '翻訳はこのコードで保存され、URL もこのコードで始まるため、変更できません。',
        'is_default' => 'この言語のページは URL に言語の接頭辞が付かず、翻訳がない箇所にはこの言語のテキストが表示されます。',
    ],
    'switch' => 'コンテンツの言語',
];

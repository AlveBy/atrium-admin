<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => '添加语言',
    'fields' => [
        'name' => '名称',
        'code' => '代码',
        'active' => '已启用',
        'is_default' => '默认',
        'sort' => '排序',
    ],
    'hints' => [
        'name' => '访客在语言切换器中看到的名称，例如：English, Deutsch, Español。',
        'code' => '两到三个小写拉丁字母，可带地区：en, de, pt-br。它会成为 URL 的开头（/de/…），之后无法更改。',
        'code_fixed' => '翻译按此代码存储，URL 也以它开头，因此无法更改。',
        'is_default' => '该语言的页面 URL 中不带语言前缀；缺少翻译的地方显示该语言的文本。',
    ],
    'switch' => '内容语言',
];

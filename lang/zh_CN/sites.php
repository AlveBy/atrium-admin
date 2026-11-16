<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => '添加站点',
    'add_domain' => '添加域名',
    'fields' => [
        'name' => '名称',
        'code' => '代码',
        'domains' => '域名',
        'languages' => '语言',
        'default_language' => '默认语言',
        'active' => '已启用',
        'is_default' => '默认站点',
        'sort' => '排序',
    ],
    'hints' => [
        'code' => '小写拉丁字母、数字、连字符：main, shop, de。之后无法更改。',
        'domains' => '站点可通过这些域名访问：example.com, www.example.com。第一个为主域名。',
        'languages' => '此站点的内容语言。均不选择则表示所有已启用的语言。',
        'default_language' => '该语言的页面在 URL 中不带语言前缀。留空则使用整个面板的默认语言。',
        'is_default' => '在没有被其他站点占用的任何域名以及 localhost 上打开。',
    ],
    'errors' => [
        'domain_invalid' => '“:domain”不是有效的域名。',
        'domain_taken' => '域名 :domain 已被站点“:site”使用。',
    ],
];

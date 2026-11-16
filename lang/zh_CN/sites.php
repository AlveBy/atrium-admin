<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => '添加站点',
    'tabs' => [
        'site' => '站点',
        'codes' => '统计与代码',
    ],
    'add_domain' => '添加域名',
    'fields' => [
        'name' => '名称',
        'code' => '代码',
        'domains' => '域名',
        'folder' => '目录',
        'languages' => '语言',
        'default_language' => '默认语言',
        'active' => '已启用',
        'is_default' => '默认站点',
        'sort' => '排序',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Yandex Metrica 计数器',
        'yandex_webvisor' => '会话回放（Webvisor）',
        'counters_skip_admins' => '不统计管理员',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => '<head> 中的代码',
        'body_start_code' => '<body> 开头的代码',
        'body_end_code' => '</body> 前的代码',
    ],
    'hints' => [
        'code' => '小写拉丁字母、数字、连字符：main, shop, de。之后无法更改。',
        'domains' => '站点可通过这些域名访问：example.com, www.example.com。第一个为主域名。',
        'folder' => '用于一个域名下的多个站点：填写 site2，此站点将在 example.com/site2/ 打开。留空表示站点位于其域名的根目录。没有域名的站点在任何域名下都位于此目录。',
        'languages' => '此站点的内容语言。均不选择则表示所有已启用的语言。',
        'default_language' => '该语言的页面在 URL 中不带语言前缀。留空则使用整个面板的默认语言。',
        'is_default' => '在没有被其他站点占用的任何域名以及 localhost 上打开。',
        'google_tag_id' => 'Google Analytics 4 填 G-XXXXXXXXXX，Tag Manager 填 GTM-XXXXXXX。只需填写 ID：计数器代码会自动添加到每个页面。',
        'yandex_metrika_id' => '只需填写 Metrica 设置中的计数器编号：代码会自动添加到每个页面。',
        'counters_skip_admins' => '已登录的管理员看到的页面不含 Google Tag 和 Yandex Metrica，以免其自身访问影响统计数据。验证标签和下方的代码始终会输出。',
        'verification' => '来自 :name meta 标签的代码。也可以粘贴整个标签。',
        'snippet' => '原样添加到此站点的每个页面：聊天小部件、像素或任何其他计数器。',
        'robots_txt' => '留空表示允许机器人抓取所有内容。如果没有“Sitemap:”行，会自动添加指向此站点 sitemap.xml 的一行。',
        'robots_not_production' => '此服务器不是生产环境（APP_ENV=:env），因此无论此处填写什么，robots.txt 都会禁止索引。',
        'robots_file_exists' => '存在文件 public/robots.txt：Web 服务器会返回该文件而不是此处的文本。请删除该文件。',
    ],
    'errors' => [
        'domain_invalid' => '“:domain”不是有效的域名。',
        'domain_taken' => '域名 :domain 已被站点“:site”使用。',
        'folder_invalid' => '“:folder”不是有效的目录：只能使用拉丁字母、数字、连字符和下划线，各部分用斜杠分隔。',
        'folder_reserved' => '目录 :folder 是面板自身的地址。',
        'folder_default' => '默认站点响应所有未被其他站点占用的地址，因此必须位于根目录。请清空目录，或将其他站点设为默认站点。',
        'folder_taken' => '地址 :address 已被站点“:site”使用。',
    ],
];

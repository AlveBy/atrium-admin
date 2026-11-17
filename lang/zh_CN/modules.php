<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The installed module packages and their settings.
 */
return [
    'title' => '模块',
    'index_hint' => '本站已安装的模块包。有设置的模块会显示进入设置的按钮。',
    'empty' => '尚未安装任何模块包。',
    'module' => '模块',
    'package' => '包',
    'settings' => '设置',
    'settings_title' => ':module：设置',
    'per_site' => '这是站点“:site”的设置。要设置其他站点，请在页眉中选择该站点。',
    'saved' => '设置已保存。',
    'enable' => '开启',
    'disable' => '关闭',
    'enabled' => '已开启 :module。',
    'disabled' => '已关闭 :module。其数据和设置会保留。',
    'parent_off' => '请先开启 :parent。',
    'config_locked' => '无法编辑 config/atrium.php。',
    'required_title' => '已引入，未安装',
    'required_hint' => 'composer.json 中尚未安装的包。安装命令：',
    'catalogue_title' => '目录',
    'catalogue_hint' => '发布在 Packagist 上的 Atrium 模块。要添加模块，请在项目目录中运行：',
    'catalogue_loading' => '正在加载目录…',
    'catalogue_unavailable' => 'Packagist 没有响应，请稍后再试。',
    'catalogue_empty' => '在 Packagist 上没有找到模块。',
    'status_installed' => '已安装',
    'status_disabled' => '已关闭',
    'status_required' => '已引入',
    'downloads' => '下载 :count 次',
];

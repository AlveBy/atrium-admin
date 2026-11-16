<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => '清除',

    'content' => [
        'mode_text' => '文本',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => '可视化编辑器',
    ],

    'editor' => [
        'placeholder' => '开始输入…',
        'format' => '文本样式',
        'format_paragraph' => '正文',
        'format_h1' => '标题 1',
        'format_h2' => '标题 2',
        'format_h3' => '标题 3',
        'format_h4' => '标题 4',
        'format_h5' => '标题 5',
        'format_h6' => '标题 6',
        'format_blockquote' => '引用',
        'font_size' => '字号',
        'font_size_default' => '字号',
        'font_size_pt' => ':size pt',
        'text_color' => '文字颜色',
        'bold' => '粗体',
        'bold_shortcut' => '粗体 (Ctrl+B)',
        'italic' => '斜体',
        'italic_shortcut' => '斜体 (Ctrl+I)',
        'underline' => '下划线',
        'underline_shortcut' => '下划线 (Ctrl+U)',
        'strike' => '删除线',
        'link' => '链接',
        'link_prompt' => '链接 URL：',
        'bullet_list' => '项目符号列表',
        'ordered_list' => '编号列表',
        'heading' => '标题',
        'image' => '图片',
        'file' => '文件 (PDF, DOC, XLS)',
        'align_left' => '左对齐',
        'align_center' => '居中',
        'align_right' => '右对齐',
        'align_justify' => '两端对齐',
        'table_insert' => '插入表格',
        'table_add_row' => '添加行',
        'table_delete_row' => '删除行',
        'table_add_column' => '添加列',
        'table_delete_column' => '删除列',
        'table_merge_split' => '合并或拆分单元格',
        'table_delete' => '删除表格',
    ],

    'upload' => [
        'from_library' => '从媒体库选择',
        'progress' => '正在上传… :percent%',
        'too_big' => '文件超过最大大小（:max KB）。',
        'too_large' => '文件过大 — 服务器拒绝了上传。',
        'network_error' => '无法连接到服务器。请检查网络连接。',
        'aborted' => '上传已取消。',
        'no_url' => '服务器未返回已上传文件的链接。',
        'http_error' => '文件上传出错（HTTP :status）。',
        'failed' => '无法上传文件。',
        'request_failed' => '请求失败',
        'session_expired' => '您的会话已过期。请刷新页面并重新登录。',
    ],

    'gallery' => [
        'add_from_library' => '从媒体库添加',
        'counter' => ':current / :count 张图片',
        'file_too_big' => '文件 :name 超过最大大小 :max KB',
        'upload_failed' => '无法上传文件 :name',
    ],

    'belongs_to_many' => [
        'clear_all' => '全部清除',
        'no_options' => '没有可用选项',
    ],

    'multi_select' => [
        'nothing_found' => '未找到任何内容',
    ],

    'map' => [
        'latitude' => '纬度',
        'longitude' => '经度',
        'open_on_map' => '在地图上打开',
        'parse_error' => '无法识别坐标。格式应为：纬度,经度 — 例如 :example',
    ],

    'repeatable' => [
        'item' => '项',
        'item_title' => ':label #:number',
        'empty' => '暂无项。点击“:add”添加。',
        'move_up' => '上移',
        'move_down' => '下移',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => '根据名称生成',
        'url_label' => 'URL：',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => '名称',
        'code' => '代码',
        'active' => '启用',
        'active_from' => '启用开始',
        'active_to' => '启用结束',
        'sort' => '排序',
        'section' => '分类',
        'parent_section' => '上级分类',
        'preview_picture' => '预览图',
        'preview_text' => '预览文本',
        'detail_picture' => '详情图',
        'detail_text' => '详情文本',
        'description' => '描述',
        'picture' => '图片',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],

    'tabs' => [
        'label' => '标签页',
        'main' => '常规',
        'section' => '分类',
        'seo' => 'SEO',
        'additional' => '其他',
        'custom_fields' => '自定义字段',
        'element_properties' => '条目属性',
        'preview' => '预览',
        'detail' => '详情',
        'sections' => '分类',
        'description' => '描述',
    ],

    'validation' => [
        'own_ancestor' => '分类不能放在其自身或其子分类之下。',
    ],

    'tree' => [
        'search_placeholder' => '搜索…',
        'top_level' => '顶级',
        'add' => '添加项',
        'empty' => '还没有项。点击“:add”创建第一项。',
        'untitled' => '无标题',
        'drag' => '拖动以移动',
        'move_up' => '上移',
        'move_down' => '下移',
        'indent' => '移入上方的项',
        'outdent' => '移出父项',
        'add_child' => '添加子项',
        'invalid' => '项列表已损坏。请刷新页面后重试。',
        'too_many' => '总共最多 :max 项。',
        'too_deep' => '最多嵌套 :max 层。',
        'item_error' => '项“:item”：:message',
    ],

    'link' => [
        'none' => '无链接',
        'url' => '地址',
        'record' => '模块页面',
        'module' => '模块',
        'search' => '按名称或 ID 查找…',
        'nothing' => '未找到任何内容。',
        'failed' => '无法加载列表，请重试。',
        'change' => '更改',
        'invalid_url' => '请输入以 /、http://、https://、mailto: 或 tel: 开头的地址。',
        'invalid_record' => '请选择仍然存在的记录。',
    ],
];

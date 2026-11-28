<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => '页面编辑器：:title',
    'open' => '打开页面编辑器',
    'back' => '返回表单',
    'save_draft' => '保存草稿',
    'publish' => '发布',
    'failed' => '操作失败。请检查网络连接后重试。',
    'leave' => '更改尚未保存。离开编辑器并放弃这些更改吗？',
    'stale' => '此草稿开始后，已发布的页面发生了变化。发布时会先询问，再替换这些变化。',
    'hidden' => '此页面已停用：发布后也不会显示在网站上。',
    'palette' => '区块',
    'palette_hint' => '将区块拖到某一列中，或点按它，放到所选项之后。',
    'add_type' => '添加区块：:type',
    'column_full' => '这里放不下更多区块。',
    'structure' => '页面结构',
    'drop_here' => '将区块拖到这里',
    'has_error' => '错误',
    'not_drawn' => '页面上暂未显示',
    'settings' => '设置',
    'nothing_selected' => '选择分区、列或区块以修改其设置。',
    'move_to' => '移动到列',
    'move' => '移动',
    'status' => [
        'published' => '已发布',
        'unsaved' => '有未保存的更改',
        'draft' => '草稿保存于 :date，尚未发布',
        'draft_by' => '草稿由 :author 保存于 :date，尚未发布',
    ],
    'notices' => [
        'draft' => '草稿已保存。网站仍显示已发布的页面。',
        'publish' => '已发布。',
        'discard' => '更改已放弃。',
        'version' => '该版本已在编辑器中打开。发布后才会显示在网站上。',
    ],
    'discard' => [
        'button' => '放弃更改',
        'title' => '放弃这些更改吗？',
        'question' => '草稿和未保存的更改将被删除，编辑器回到已发布的页面。',
    ],
    'conflicts' => [
        'draft' => '在你打开此页面之后，有人保存或删除了它的草稿。',
        'published' => '本次编辑开始后，已发布的页面发生了变化。',
    ],
    'conflict' => [
        'title' => '页面已在别处被修改',
        'hint' => '载入较新的页面会丢弃你未保存的更改。继续操作则用你的更改替换对方的更改。',
        'reload' => '载入较新的页面',
        'force' => '仍然继续',
    ],
    'versions' => [
        'button' => '版本',
        'title' => '页面的历史版本',
        'hint' => '打开某个版本后，在发布之前网站不会有任何变化。',
        'replaces' => '打开某个版本会替换你未保存的更改。',
        'open' => '在编辑器中打开',
        'current' => '当前已发布',
    ],
    'preview' => [
        'title' => '预览',
        'updating' => '正在更新…',
        'hint' => '点击页面以选择其中的部分。',
        'failed' => '无法生成预览。',
        'selected' => '已选择：:place',
    ],
    'said' => [
        'added' => '已添加：:place',
        'moved' => '已移动，现在位于：:place',
        'removed' => '已删除：:place',
    ],
    'count' => [
        'sections' => ':count 个分区',
        'blocks' => ':count 个区块',
        'empty' => '页面还没有区块。',
    ],
];

<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The ways to look at a module's list besides the table: the switcher, the
 * kanban board and the calendar.
 */

return [
    'label' => '列表视图',
    'table' => '表格',

    'kanban' => [
        'name' => '看板',
        'no_value' => '无值',
        'count' => '卡片：:count',
        'empty' => '没有卡片',
        'more' => '显示更多',
        'move_to' => '移到列',
        'failed' => '无法移动该卡片。',
    ],

    'calendar' => [
        'name' => '日历',
        'month' => '月',
        'week' => '周',
        'list' => '列表',
        'modes' => '日历模式',
        'today' => '今天',
        'previous' => '上一个',
        'next' => '下一个',
        'add' => '在 :date 添加',
        'empty' => '这些日子没有内容',
        'truncated' => '仅显示前 :count 条记录。请用搜索或筛选缩小列表。',
        'no_date' => '该记录没有日期，因此无法移动。',
        'move_to' => '移到日期',
        'failed' => '无法移动该记录。',
    ],
];

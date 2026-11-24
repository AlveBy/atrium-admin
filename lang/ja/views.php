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
    'label' => '一覧の表示',
    'table' => 'テーブル',

    'kanban' => [
        'name' => 'カンバン',
        'no_value' => '値なし',
        'count' => 'カード：:count',
        'empty' => 'カードはありません',
        'more' => 'さらに表示',
        'move_to' => '列に移動',
        'failed' => 'カードを移動できませんでした。',
    ],

    'calendar' => [
        'name' => 'カレンダー',
        'month' => '月',
        'week' => '週',
        'list' => 'リスト',
        'modes' => 'カレンダーの表示',
        'today' => '今日',
        'previous' => '前へ',
        'next' => '次へ',
        'add' => ':date に追加',
        'empty' => 'この期間には何もありません',
        'truncated' => '最初の :count 件のみ表示しています。検索またはフィルターで絞り込んでください。',
        'no_date' => '日付がないため、このレコードは移動できません。',
        'failed' => 'レコードを移動できませんでした。',
    ],
];

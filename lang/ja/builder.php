<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'ページエディター: :title',
    'open' => 'ページエディターを開く',
    'back' => 'フォームに戻る',
    'save_draft' => '下書きを保存',
    'publish' => '公開',
    'failed' => 'うまくいきませんでした。接続を確認してもう一度お試しください。',
    'leave' => '変更は保存されていません。エディターを離れて変更を破棄しますか？',
    'stale' => 'この下書きを始めた後に、公開中のページが変更されました。公開時には、その変更を置き換える前に確認します。',
    'hidden' => 'このページは無効です。公開してもサイトには表示されません。',
    'palette' => 'ブロック',
    'palette_hint' => 'ブロックを列にドラッグするか、押して選択中の項目の後に追加します。',
    'add_type' => 'ブロックを追加: :type',
    'column_full' => 'ここにはこれ以上ブロックを置けません。',
    'structure' => 'ページの構成',
    'drop_here' => 'ここにブロックをドラッグ',
    'has_error' => 'エラー',
    'not_drawn' => 'まだページに表示されていません',
    'settings' => '設定',
    'nothing_selected' => '設定を変更するには、セクション、列、またはブロックを選択してください。',
    'move_to' => '移動先の列',
    'move' => '移動',
    'status' => [
        'published' => '公開済み',
        'unsaved' => '未保存の変更があります',
        'draft' => '下書き保存 :date（未公開）',
        'draft_by' => '下書き保存 :date、保存者 :author（未公開）',
    ],
    'notices' => [
        'draft' => '下書きを保存しました。サイトには公開中のページが表示されます。',
        'publish' => '公開しました。',
        'discard' => '変更を破棄しました。',
        'version' => 'このバージョンをエディターで開きました。サイトに反映するには公開してください。',
    ],
    'discard' => [
        'button' => '変更を破棄',
        'title' => '変更を破棄しますか？',
        'question' => '下書きと未保存の変更は削除され、エディターは公開中のページに戻ります。',
    ],
    'conflicts' => [
        'draft' => 'ページを開いた後に、ほかの人がこのページの下書きを保存または削除しました。',
        'published' => 'この編集を始めた後に、公開中のページが変更されました。',
    ],
    'conflict' => [
        'title' => 'ページが別の場所で変更されました',
        'hint' => '新しいページを読み込むと、未保存の変更は失われます。続行すると、相手の変更があなたの変更に置き換わります。',
        'reload' => '新しいページを読み込む',
        'force' => 'このまま続行',
    ],
    'versions' => [
        'button' => 'バージョン',
        'title' => 'ページの以前のバージョン',
        'hint' => 'バージョンを開いても、公開するまでサイトは変わりません。',
        'replaces' => 'バージョンを開くと、未保存の変更は置き換えられます。',
        'open' => 'エディターで開く',
        'current' => '公開中',
    ],
    'preview' => [
        'title' => 'プレビュー',
        'updating' => '更新中…',
        'hint' => 'ページをクリックすると、その部分を選択できます。',
        'failed' => 'プレビューを表示できませんでした。',
        'selected' => '選択中: :place',
    ],
    'said' => [
        'added' => '追加しました: :place',
        'moved' => '移動しました。現在の位置: :place',
        'removed' => '削除しました: :place',
    ],
    'count' => [
        'sections' => ':count セクション',
        'blocks' => ':count ブロック',
        'empty' => 'このページにはまだブロックがありません。',
    ],
];

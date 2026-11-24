<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'スーパー管理者',
    'superadmin_hint' => 'ロールがなくてもすべての操作ができ、ロールと他のスーパー管理者を管理できるのはスーパー管理者だけです。',
    'roles' => 'ロール',
    'roles_hint' => '管理者は、チェックしたロールのいずれかで許可された操作ができます。',
    'site_roles' => 'サイトごとのロール',
    'site_roles_hint' => 'パネルでそのサイトを選んでいる間だけ、上のロールに加えて有効です。あるサイトは編集し、別のサイトは閲覧のみにできます。',
    'forbidden' => 'あなたのロールでは許可されていません。',
    'last_superadmin' => 'これは最後の有効なスーパー管理者です。ロールを管理する人が少なくとも一人必要です。',
    'saved' => 'ロールを保存しました。',
    'deleted' => 'ロールを削除しました。',
    'create_title' => '新しいロール',
    'edit_title' => 'ロール: :name',
    'name' => '名前',
    'permissions' => '権限',
    'section' => 'セクション',
    'permissions_count' => '権限',
    'users_count' => '管理者',
    'select_all' => 'すべて選択',
    'clear' => 'すべて解除',
    'all' => 'すべて',
    'other' => 'その他',
    'matrix_hint' => '「閲覧」は一覧とレコードを読み取り専用で開きます。作成・編集・削除にも必要なので、一緒にチェックされます。',
    'index_hint' => 'ロールは権限のセットです。ロールは管理者のページで割り当てます。スーパー管理者にはロールは不要で、すべての操作ができます。',
    'empty' => 'まだロールはありません。',
    'delete_title' => 'ロールを削除しますか？',
    'delete_confirm' => 'このロールを持つ管理者は、このロールで許可されていた操作ができなくなります。',

    'actions' => [
        'view' => '閲覧',
        'create' => '作成',
        'update' => '編集',
        'delete' => '削除',
        'use' => 'トークンの発行と使用',
        'settings' => '設定',
        'import' => 'インポート',
        'export' => 'エクスポート',
    ],
];

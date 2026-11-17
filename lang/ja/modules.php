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
    'title' => 'モジュール',
    'index_hint' => 'このサイトにインストールされているモジュールパッケージです。設定があるモジュールには設定へのボタンがあります。',
    'empty' => 'モジュールパッケージはインストールされていません。',
    'module' => 'モジュール',
    'package' => 'パッケージ',
    'settings' => '設定',
    'settings_title' => ':module: 設定',
    'per_site' => 'これはサイト「:site」の設定です。別のサイトを設定するには、ヘッダーでそのサイトを選んでください。',
    'saved' => '設定を保存しました。',
    'enable' => 'オンにする',
    'disable' => 'オフにする',
    'enabled' => ':module をオンにしました。',
    'disabled' => ':module をオフにしました。データと設定は残ります。',
    'parent_off' => '先に :parent をオンにしてください。',
    'config_locked' => 'config/atrium.php を編集できませんでした。',
    'required_title' => '必須だが未インストール',
    'required_hint' => 'composer.json にあり、まだインストールされていないパッケージです。インストール方法:',
    'catalogue_title' => 'カタログ',
    'catalogue_hint' => 'Packagist で公開されている Atrium モジュールです。追加するにはプロジェクトフォルダで実行します:',
    'catalogue_loading' => 'カタログを読み込み中…',
    'catalogue_unavailable' => 'Packagist から応答がありません。後でもう一度お試しください。',
    'catalogue_empty' => 'Packagist にモジュールが見つかりません。',
    'status_installed' => 'インストール済み',
    'status_disabled' => 'オフ',
    'status_required' => '必須',
    'downloads' => 'ダウンロード数 :count',
];

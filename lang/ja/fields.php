<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'クリア',

    'content' => [
        'mode_text' => 'テキスト',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'ビジュアルエディター',
    ],

    'editor' => [
        'placeholder' => '入力を開始…',
        'format' => 'テキストスタイル',
        'format_paragraph' => '標準テキスト',
        'format_h1' => '見出し 1',
        'format_h2' => '見出し 2',
        'format_h3' => '見出し 3',
        'format_h4' => '見出し 4',
        'format_h5' => '見出し 5',
        'format_h6' => '見出し 6',
        'format_blockquote' => '引用',
        'font_size' => 'フォントサイズ',
        'font_size_default' => 'サイズ',
        'font_size_pt' => ':size pt',
        'text_color' => '文字の色',
        'bold' => '太字',
        'bold_shortcut' => '太字 (Ctrl+B)',
        'italic' => '斜体',
        'italic_shortcut' => '斜体 (Ctrl+I)',
        'underline' => '下線',
        'underline_shortcut' => '下線 (Ctrl+U)',
        'strike' => '取り消し線',
        'link' => 'リンク',
        'link_prompt' => 'リンク先 URL:',
        'bullet_list' => '箇条書き',
        'ordered_list' => '番号付きリスト',
        'heading' => '見出し',
        'image' => '画像',
        'file' => 'ファイル (PDF, DOC, XLS)',
        'align_left' => '左揃え',
        'align_center' => '中央揃え',
        'align_right' => '右揃え',
        'align_justify' => '両端揃え',
        'table_insert' => '表を挿入',
        'table_add_row' => '行を追加',
        'table_delete_row' => '行を削除',
        'table_add_column' => '列を追加',
        'table_delete_column' => '列を削除',
        'table_merge_split' => 'セルの結合/分割',
        'table_delete' => '表を削除',
    ],

    'upload' => [
        'from_library' => 'ライブラリから',
        'progress' => 'アップロード中… :percent%',
        'too_big' => 'ファイルが最大サイズ（:max KB）を超えています。',
        'too_large' => 'ファイルが大きすぎます — サーバーがアップロードを拒否しました。',
        'network_error' => 'サーバーに接続できません。接続を確認してください。',
        'aborted' => 'アップロードをキャンセルしました。',
        'no_url' => 'サーバーからアップロードしたファイルのリンクが返されませんでした。',
        'http_error' => 'ファイルのアップロードエラー（HTTP :status）。',
        'failed' => 'ファイルをアップロードできませんでした。',
        'request_failed' => 'リクエストに失敗しました',
        'session_expired' => 'セッションの有効期限が切れました。ページを再読み込みして、もう一度ログインしてください。',
    ],

    'gallery' => [
        'add_from_library' => 'ライブラリから追加',
        'counter' => ':current / :count 枚の画像',
        'file_too_big' => 'ファイル :name が最大サイズ :max KB を超えています',
        'upload_failed' => 'ファイル :name をアップロードできませんでした',
    ],

    'belongs_to_many' => [
        'clear_all' => 'すべてクリア',
        'no_options' => '選択肢がありません',
    ],

    'map' => [
        'latitude' => '緯度',
        'longitude' => '経度',
        'open_on_map' => '地図で開く',
        'parse_error' => '座標を読み取れませんでした。形式は 緯度,経度 です — 例: :example',
    ],

    'repeatable' => [
        'item' => '項目',
        'item_title' => ':label #:number',
        'empty' => '項目がありません。「:add」をクリックして追加してください。',
        'move_up' => '上へ移動',
        'move_down' => '下へ移動',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => '名前から生成',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => '検索…',
        'top_level' => '最上位',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => '名前',
        'code' => 'コード',
        'active' => '有効',
        'active_from' => '有効期間の開始',
        'active_to' => '有効期間の終了',
        'sort' => '並び順',
        'section' => 'セクション',
        'parent_section' => '親セクション',
        'preview_picture' => 'プレビュー画像',
        'preview_text' => 'プレビューテキスト',
        'detail_picture' => '詳細画像',
        'detail_text' => '詳細テキスト',
        'description' => '説明',
        'picture' => '画像',
        'created_at' => '作成日',
        'updated_at' => '更新日',
    ],

    'tabs' => [
        'label' => 'タブ',
        'main' => '全般',
        'section' => 'セクション',
        'seo' => 'SEO',
        'additional' => '追加情報',
        'custom_fields' => 'カスタムフィールド',
        'element_properties' => '項目のプロパティ',
        'preview' => 'プレビュー',
        'detail' => '詳細',
        'sections' => 'セクション',
        'description' => '説明',
    ],

    'validation' => [
        'own_ancestor' => 'セクションをそれ自身またはその下位セクションの中に配置することはできません。',
    ],
];

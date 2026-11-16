<?php

declare(strict_types=1);

/*
 * Form fields, the WYSIWYG editor and form tabs.
 */
return [
    'clear' => 'Clear',

    'content' => [
        'mode_text' => 'Text',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Visual editor',
    ],

    'editor' => [
        'placeholder' => 'Start typing…',
        'format' => 'Text style',
        'format_paragraph' => 'Normal text',
        'format_h1' => 'Heading 1',
        'format_h2' => 'Heading 2',
        'format_h3' => 'Heading 3',
        'format_h4' => 'Heading 4',
        'format_h5' => 'Heading 5',
        'format_h6' => 'Heading 6',
        'format_blockquote' => 'Quote',
        'font_size' => 'Font size',
        'font_size_default' => 'Size',
        'font_size_pt' => ':size pt',
        'text_color' => 'Text colour',
        'bold' => 'Bold',
        'bold_shortcut' => 'Bold (Ctrl+B)',
        'italic' => 'Italic',
        'italic_shortcut' => 'Italic (Ctrl+I)',
        'underline' => 'Underline',
        'underline_shortcut' => 'Underline (Ctrl+U)',
        'strike' => 'Strikethrough',
        'link' => 'Link',
        'link_prompt' => 'Link URL:',
        'bullet_list' => 'Bulleted list',
        'ordered_list' => 'Numbered list',
        'heading' => 'Heading',
        'image' => 'Image',
        'file' => 'File (PDF, DOC, XLS)',
        'align_left' => 'Align left',
        'align_center' => 'Align centre',
        'align_right' => 'Align right',
        'align_justify' => 'Justify',
        'table_insert' => 'Insert table',
        'table_add_row' => 'Add row',
        'table_delete_row' => 'Delete row',
        'table_add_column' => 'Add column',
        'table_delete_column' => 'Delete column',
        'table_merge_split' => 'Merge or split cells',
        'table_delete' => 'Delete table',
    ],

    'upload' => [
        'from_library' => 'From library',
        'progress' => 'Uploading… :percent%',
        'too_big' => 'The file exceeds the maximum size (:max KB).',
        'too_large' => 'The file is too large — the server rejected the upload.',
        'network_error' => 'Could not reach the server. Check your connection.',
        'aborted' => 'Upload cancelled.',
        'no_url' => 'The server did not return a link to the uploaded file.',
        'http_error' => 'File upload error (HTTP :status).',
        'failed' => 'Could not upload the file.',
        'request_failed' => 'The request failed',
        'session_expired' => 'Your session has expired. Refresh the page and sign in again.',
    ],

    'gallery' => [
        'add_from_library' => 'Add from library',
        'counter' => ':current / :count image|:current / :count images',
        'file_too_big' => 'The file :name exceeds the maximum size of :max KB',
        'upload_failed' => 'Could not upload the file :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Clear all',
        'no_options' => 'No options available',
    ],

    'map' => [
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'open_on_map' => 'Open on map',
        'parse_error' => 'Could not parse the coordinates. Expected format: latitude,longitude — for example :example',
    ],

    'repeatable' => [
        'item' => 'Item',
        'item_title' => ':label #:number',
        'empty' => 'No items. Click “:add” to add one.',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Generate from name',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Search…',
        'top_level' => 'Top level',
    ],

    'tabs' => [
        'label' => 'Tabs',
        'main' => 'General',
        'section' => 'Section',
        'seo' => 'SEO',
        'additional' => 'Additional',
        'custom_fields' => 'Custom fields',
        'element_properties' => 'Element properties',
    ],

    'validation' => [
        'own_ancestor' => 'A section cannot be placed inside itself or its own subsection.',
    ],
];

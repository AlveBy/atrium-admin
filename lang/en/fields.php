<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

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

    'multi_select' => [
        'nothing_found' => 'Nothing found',
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

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Name',
        'code' => 'Code',
        'active' => 'Active',
        'active_from' => 'Active from',
        'active_to' => 'Active until',
        'sort' => 'Sort',
        'section' => 'Section',
        'parent_section' => 'Parent section',
        'preview_picture' => 'Preview picture',
        'preview_text' => 'Preview text',
        'detail_picture' => 'Detail picture',
        'detail_text' => 'Detail text',
        'description' => 'Description',
        'picture' => 'Picture',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'tabs' => [
        'label' => 'Tabs',
        'main' => 'General',
        'section' => 'Section',
        'seo' => 'SEO',
        'additional' => 'Additional',
        'custom_fields' => 'Custom fields',
        'element_properties' => 'Element properties',
        'preview' => 'Preview',
        'detail' => 'Details',
        'sections' => 'Sections',
        'description' => 'Description',
    ],

    'validation' => [
        'own_ancestor' => 'A section cannot be placed inside itself or its own subsection.',
    ],

    'tree' => [
        'search_placeholder' => 'Search…',
        'top_level' => 'Top level',
        'add' => 'Add item',
        'empty' => 'No items yet. Press “:add” to create the first one.',
        'untitled' => 'Untitled',
        'drag' => 'Drag to move',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'indent' => 'Move into the item above',
        'outdent' => 'Move out of the parent item',
        'add_child' => 'Add a nested item',
        'invalid' => 'The list of items is broken. Reload the page and try again.',
        'too_many' => 'No more than :max items in total.',
        'too_deep' => 'Items can be nested no deeper than :max levels.',
        'item_error' => 'Item “:item”: :message',
    ],

    'link' => [
        'none' => 'No link',
        'url' => 'Address',
        'record' => 'Page of a module',
        'module' => 'Module',
        'search' => 'Find by name or ID…',
        'nothing' => 'Nothing found.',
        'failed' => 'Could not load the list. Try again.',
        'change' => 'Change',
        'invalid_url' => 'Enter an address starting with /, http://, https://, mailto: or tel:.',
        'invalid_record' => 'Choose a record that still exists.',
    ],
];

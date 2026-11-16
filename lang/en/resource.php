<?php

declare(strict_types=1);

/*
 * Generic module pages: list, create and edit forms, the data table and the
 * standard SEO fields.
 */
return [
    'created' => 'Record created.',
    'updated' => 'Record updated.',
    'deleted' => 'Record deleted.',
    'cannot_delete' => 'This record cannot be deleted.',
    'delete_failed' => 'Could not delete the record.',
    'add_item' => 'Add item',

    'title' => [
        'create' => 'Create :label',
        'edit' => 'Edit: :title',
    ],

    'index' => [
        'total' => 'Total records: :count',
        'search_placeholder' => 'Search…',
        'search_button' => 'Find',
    ],

    'form' => [
        'breadcrumb_create' => 'New',
        'empty_tab' => 'No fields to show',
        'read_only' => 'Read-only: your role lets you view this record but not change it.',
    ],

    'delete' => [
        'title' => 'Confirm deletion',
        'confirm' => 'Are you sure you want to delete this record?',
    ],

    'table' => [
        'empty' => 'No data',
    ],

    'pagination' => [
        'showing' => 'Showing :from–:to of :total',
    ],

    'seo' => [
        'title' => 'META TITLE',
        'title_hint' => 'Browser tab title. Leave empty to use the name',
        'keywords' => 'META KEYWORDS',
        'keywords_hint' => 'Keywords, separated by commas',
        'description' => 'META DESCRIPTION',
        'description_hint' => 'Description for search results. Leave empty to use the preview text',
        'page_title' => 'Page heading (H1)',
        'page_title_hint' => 'Heading on the page itself. Leave empty to use the name',
        'preview_picture_alt' => 'Preview picture: ALT',
        'preview_picture_title' => 'Preview picture: TITLE',
        'preview_picture_file_name' => 'Preview picture: file name',
        'detail_picture_alt' => 'Detail picture: ALT',
        'detail_picture_title' => 'Detail picture: TITLE',
        'detail_picture_file_name' => 'Detail picture: file name',
        'tags' => 'Tags',
        'tags_hint' => 'Words or phrases, separated by commas',
        'og_image' => 'OG image',
        'og_image_hint' => 'Preview picture for sharing on social networks',
    ],
];

<?php

declare(strict_types=1);

/*
 * File manager (media library) and uploads.
 */
return [
    'title' => 'Media library',
    'filters' => [
        'all' => 'All',
        'images' => 'Images',
        'documents' => 'Documents',
    ],
    'all_collections' => 'All collections',
    'search_placeholder' => 'Search files…',
    'uploading' => 'Uploading files…',
    'empty' => 'No files found',
    'load_more' => 'Load more',
    'select_hint' => 'Select a file or upload a new one',
    'insert' => 'Insert',
    'remove_confirm' => 'Remove ":name" from the library?',

    'errors' => [
        'required' => 'Choose a file to upload.',
        'upload_broken' => 'The file did not upload. Try again.',
        'too_big' => 'The file is too large. Maximum size: :max MB.',
        'list_failed' => 'Could not load the file list',
        'upload_failed' => 'Could not upload :name',
        'remove_failed' => 'Could not remove ":name" from the library',
        'delete_failed' => 'Could not delete the file',
        'type_not_allowed' => 'File type not allowed. Allowed: :types',
        'not_found' => 'File not found.',
    ],

    'removed' => 'File removed from the library.',
    'uploads_collection' => 'Uploaded in the admin panel',
];

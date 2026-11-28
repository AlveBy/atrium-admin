<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * The page editor of a layout field: its palette, structure, settings,
 * preview, drafts and versions (docs/site-builder.md).
 */

return [
    'title' => 'Page editor: :title',
    'open' => 'Open the page editor',
    'back' => 'Back to the form',
    'save_draft' => 'Save draft',
    'publish' => 'Publish',
    'failed' => 'It did not work. Check the connection and try again.',
    'leave' => 'The changes are not saved. Leave the editor and lose them?',
    'stale' => 'The published page changed after this draft was started. Publishing asks before it replaces those changes.',
    'hidden' => 'This page is switched off: publishing does not put it on the site.',
    'palette' => 'Blocks',
    'palette_hint' => 'Drag a block into a column, or press it to put it after what is selected.',
    'add_type' => 'Add block: :type',
    'column_full' => 'No room for one more block here.',
    'structure' => 'Page structure',
    'drop_here' => 'Drag a block here',
    'has_error' => 'Error',
    'not_drawn' => 'Not shown on the page yet',
    'settings' => 'Settings',
    'nothing_selected' => 'Select a section, a column or a block to change its settings.',
    'move_to' => 'Move to column',
    'move' => 'Move',
    'status' => [
        'published' => 'Published',
        'unsaved' => 'Unsaved changes',
        'draft' => 'Draft saved on :date, not published',
        'draft_by' => 'Draft saved on :date by :author, not published',
    ],
    'notices' => [
        'draft' => 'The draft is saved. The site shows the published page.',
        'publish' => 'Published.',
        'discard' => 'The changes are discarded.',
        'version' => 'The version is open in the editor. Publish it to put it on the site.',
    ],
    'discard' => [
        'button' => 'Discard changes',
        'title' => 'Discard the changes?',
        'question' => 'The draft and the unsaved changes are removed; the editor goes back to the published page.',
    ],
    'conflicts' => [
        'draft' => 'Someone saved or discarded the draft of this page after you opened it.',
        'published' => 'The published page changed after this editing started.',
    ],
    'conflict' => [
        'title' => 'The page was changed elsewhere',
        'hint' => 'Loading the newer page drops your unsaved changes. Continuing replaces the other changes with yours.',
        'reload' => 'Load the newer page',
        'force' => 'Continue anyway',
    ],
    'versions' => [
        'button' => 'Versions',
        'title' => 'Earlier versions of the page',
        'hint' => 'Opening a version changes nothing on the site until you publish it.',
        'replaces' => 'Opening a version replaces your unsaved changes.',
        'open' => 'Open in the editor',
        'current' => 'published now',
    ],
    'preview' => [
        'title' => 'Preview',
        'updating' => 'Updating…',
        'hint' => 'Click the page to select a part of it.',
        'failed' => 'The preview could not be drawn.',
        'selected' => 'Selected: :place',
    ],
    'said' => [
        'added' => 'Added: :place',
        'moved' => 'Moved, now at: :place',
        'removed' => 'Removed: :place',
    ],
    'count' => [
        'sections' => ':count section|:count sections',
        'blocks' => ':count block|:count blocks',
        'empty' => 'The page has no blocks yet.',
    ],
];

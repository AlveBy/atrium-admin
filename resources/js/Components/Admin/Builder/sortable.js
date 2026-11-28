/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import Sortable from 'sortablejs';

/** The palette and the columns of one editor take blocks from each other. */
export const BLOCKS = 'atrium-blocks';

/**
 * Drag and drop of blocks. The pointer is followed by the page itself, not by
 * the browser's own drag and drop, so it works on touch screens too.
 *
 * Vue draws the lists, so what was dragged goes back to where it stood before
 * `onDrop` is told: the tree then moves it.
 */
export function sortable(element, { onDrop, ...options }) {
    let anchor = null;

    return Sortable.create(element, {
        animation: 120,
        forceFallback: true,
        fallbackTolerance: 4,
        fallbackOnBody: true,
        ghostClass: 'builder-ghost',
        fallbackClass: 'builder-drag',
        ...options,
        onStart: (event) => { anchor = event.item.nextSibling; },
        onEnd: (event) => {
            if (event.to === event.from && event.oldDraggableIndex === event.newDraggableIndex) return;

            // A list that copies what is dragged out of it keeps the copy in its place.
            if (event.clone?.parentNode === event.from) {
                event.clone.replaceWith(event.item);
            } else {
                event.from.insertBefore(event.item, anchor);
            }

            onDrop(event);
        },
    });
}

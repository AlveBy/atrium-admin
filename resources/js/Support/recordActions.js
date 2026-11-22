/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { router } from '@inertiajs/vue3'

// Where a record button leads (AdminController::resourceProps). A filled
// create form of the module, e.g. ?parent_id=12; a link, to the address the
// server worked out for this record (actionUrls: id => name => address). A
// server action leads nowhere: it is run by runRecordAction.
export function recordActionHref(routeName, action, item, actionUrls = {}) {
    if (action.type === 'link') {
        return actionUrls[item.id]?.[action.name] ?? null
    }

    const query = Object.fromEntries(
        Object.entries(action.fill || {}).map(([field, attribute]) => [field, item[attribute]])
    )

    return route(`${routeName}.create`, query)
}

// Runs a server action of a record; the answer comes back as a flash message.
export function runRecordAction(routeName, action, item) {
    router.post(route(`${routeName}.action`, { id: item.id, action: action.name }), {}, { preserveScroll: true })
}

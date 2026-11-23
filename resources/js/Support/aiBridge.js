/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { shallowReactive } from 'vue';

/**
 * Where the AI buttons of a field and its visual editor meet.
 *
 * The buttons (AiAssist) sit beside the field, the editor (ContentField) is
 * inside it, and neither is the other's parent. So each says here that it is
 * there, under the field's name:
 *
 *   editors[name]  the visual editor is on screen: { pick(), replace(piece, html) }
 *                  — AiAssist reads the selected piece from it and puts the
 *                  improved piece back, and draws no link under the field,
 *                  because the editor's toolbar has the buttons.
 *   assists[name]  the AI is set up for the field: { menu(), run(mode) }
 *                  — the toolbar draws the menu and starts the job.
 */
export const aiBridge = {
    editors: shallowReactive({}),
    assists: shallowReactive({}),
};

/** Registers `value` under `name` and returns what undoes it. */
export function enlist(kind, name, value) {
    aiBridge[kind][name] = value;

    return () => {
        // A newer registration under the same name stays.
        if (aiBridge[kind][name] === value) {
            delete aiBridge[kind][name];
        }
    };
}

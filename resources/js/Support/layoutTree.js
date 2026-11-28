/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Changes to the tree of a page built of blocks (docs/site-builder.md).
 *
 * A stored tree is never rewritten to suit the editor: what is not touched
 * stays as it came, absent keys absent and empty settings as the server sent
 * them. Every change returns a new tree; the one given is left alone.
 */
export const GRID = 12;

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

/** An empty PHP array arrives as [] where the tree means an empty object. */
export const object = (value) => (isObject(value) ? value : {});
export const list = (value) => (Array.isArray(value) ? value : []);

export const sectionsOf = (tree) => list(tree?.sections);
export const columnsOf = (section) => list(section?.columns);
export const blocksOf = (column) => list(column?.blocks);

const clone = (value) => JSON.parse(JSON.stringify(value));

let lastId = 0;

/** Unique in the tree: the time, a counter and chance. */
export function newId(prefix) {
    return `${prefix}${Date.now().toString(36)}${++lastId}${Math.random().toString(36).slice(2, 6)}`;
}

/**
 * Where a node is: its kind, the indexes down to it and the nodes on the way.
 * Null when the tree has no node with this id.
 */
export function locate(tree, id) {
    const sections = sectionsOf(tree);

    for (let s = 0; s < sections.length; s++) {
        const section = sections[s];

        if (section.id === id) {
            return { kind: 'section', s, section, node: section };
        }

        const columns = columnsOf(section);

        for (let c = 0; c < columns.length; c++) {
            const column = columns[c];

            if (column.id === id) {
                return { kind: 'column', s, c, section, column, node: column };
            }

            const blocks = blocksOf(column);

            for (let b = 0; b < blocks.length; b++) {
                if (blocks[b].id === id) {
                    return { kind: 'block', s, c, b, section, column, node: blocks[b] };
                }
            }
        }
    }

    return null;
}

/** A new tree: `mutate` gets a copy of the sections. */
function changed(tree, version, mutate) {
    const next = { ...(isObject(tree) ? clone(tree) : {}), version };
    next.sections = list(next.sections);
    mutate(next);

    return next;
}

/** The list a node sits in, in a tree that may be changed. */
function siblings(tree, found) {
    if (found.kind === 'section') {
        return [tree.sections, found.s];
    }

    const section = tree.sections[found.s];

    return found.kind === 'column'
        ? [section.columns, found.c]
        : [section.columns[found.c].blocks, found.b];
}

/** Columns of a row take equal parts of the twelve. */
function share(columns) {
    for (const column of columns) {
        column.settings = { ...object(column.settings), span: Math.floor(GRID / columns.length) };
    }
}

export function addSection(tree, version, section) {
    return changed(tree, version, (next) => next.sections.push(section));
}

export function addColumn(tree, version, sectionId, column) {
    const found = locate(tree, sectionId);

    return changed(tree, version, (next) => {
        const section = next.sections[found.s];
        section.columns = [...list(section.columns), column];
        share(section.columns);
    });
}

/** Puts a new block into a column at `index`; at the end without one. */
export function addBlock(tree, version, columnId, block, index = null) {
    const found = locate(tree, columnId);

    return changed(tree, version, (next) => {
        const column = next.sections[found.s].columns[found.c];
        column.blocks = list(column.blocks);
        column.blocks.splice(index ?? column.blocks.length, 0, block);
    });
}

/**
 * Takes a block out of its column and puts it into `columnId` at `index`,
 * counted without the block itself; at the end without one.
 */
export function moveBlock(tree, version, blockId, columnId, index = null) {
    const from = locate(tree, blockId);
    const to = locate(tree, columnId);

    return changed(tree, version, (next) => {
        const [block] = next.sections[from.s].columns[from.c].blocks.splice(from.b, 1);
        const column = next.sections[to.s].columns[to.c];
        column.blocks = list(column.blocks);
        column.blocks.splice(index ?? column.blocks.length, 0, block);
    });
}

/** One place up (-1) or down (1) among its siblings. */
export function step(tree, version, id, by) {
    const found = locate(tree, id);

    return changed(tree, version, (next) => {
        const [items, index] = siblings(next, found);
        items.splice(index + by, 0, ...items.splice(index, 1));
    });
}

export function remove(tree, version, id) {
    const found = locate(tree, id);

    return changed(tree, version, (next) => {
        const [items, index] = siblings(next, found);
        items.splice(index, 1);

        if (found.kind === 'column') {
            share(items);
        }
    });
}

export function setSetting(tree, version, id, name, value) {
    const found = locate(tree, id);

    return changed(tree, version, (next) => {
        const [items, index] = siblings(next, found);
        items[index].settings = { ...object(items[index].settings), [name]: value };
    });
}

/**
 * Where a block added without pointing goes: after the selected block, at the
 * end of the selected column or of the selected section's last column, and
 * otherwise at the end of the page. Null when there is no column for it.
 */
export function target(tree, selected) {
    const found = selected ? locate(tree, selected) : null;

    if (found?.kind === 'block') {
        return { columnId: found.column.id, index: found.b + 1 };
    }

    if (found?.kind === 'column') {
        return { columnId: found.column.id, index: blocksOf(found.column).length };
    }

    const sections = sectionsOf(tree);
    const column = columnsOf(found?.section ?? sections[sections.length - 1]).at(-1);

    return column ? { columnId: column.id, index: blocksOf(column).length } : null;
}

/** The same content: key order aside, an empty list and an empty object alike. */
function written(value) {
    if (Array.isArray(value)) {
        return value.length ? `[${value.map(written).join(',')}]` : '{}';
    }

    if (isObject(value)) {
        return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${written(value[key])}`).join(',')}}`;
    }

    return JSON.stringify(value ?? null);
}

/** Whether two trees draw the same page. A page without sections is empty, however it is written. */
export function same(one, other) {
    const print = (tree) => (sectionsOf(tree).length ? written(tree) : '');

    return print(one) === print(other);
}

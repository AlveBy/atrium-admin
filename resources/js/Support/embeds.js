/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Embeds in the visual editor: a form, a block with the phone number, a list
 * of products — whatever the modules registered (PHP: Embeds::REGISTRY).
 *
 * In the HTML an embed is an empty placeholder:
 *
 *   <div data-atrium-embed="form" data-id="3"></div>
 *
 * and the site swaps it for the real thing on every page view. In the editor
 * it is one atomic node drawn as a labelled chip. The node has to be in the
 * schema with both attributes read and written back: TipTap drops whatever
 * its schema does not know, and a text saved through the editor would lose
 * its forms (the same trap that once ate tables).
 */

import { Node, mergeAttributes } from '@tiptap/core';
import { usePage } from '@inertiajs/vue3';
import { ajaxHeaders } from './csrf';

/** `type:id` → the name of the item, kept for the page's lifetime. */
const names = new Map();

/** `type:id` → the request already on its way. */
const asked = new Map();

const key = (type, id) => `${type}:${id}`;

/** Remember a name the picker has just shown, so the chip needs no request. */
export function rememberName(type, id, name) {
  names.set(key(type, id), name);
}

/**
 * What the picker of a type offers: the type's `url` from the shared
 * `embeds` prop, with a few typed words or the ids to name.
 */
export async function fetchItems(url, params = {}) {
  const response = await fetch(`${url}?${new URLSearchParams(params)}`, {
    headers: ajaxHeaders(),
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(String(response.status));
  }

  const body = await response.json();

  return body.data || [];
}

/** The name of an item for its chip; null when it is gone or not allowed. */
export async function nameOf(type, id) {
  const known = names.get(key(type.type, id));

  if (known !== undefined) {
    return known;
  }

  if (!asked.has(key(type.type, id))) {
    asked.set(
      key(type.type, id),
      fetchItems(type.url, { ids: id })
        .then((items) => items.find((item) => item.id === id)?.label ?? null)
        .catch(() => null)
        .then((name) => {
          if (name !== null) {
            names.set(key(type.type, id), name);
          }

          return name;
        }),
    );
  }

  return asked.get(key(type.type, id));
}

/**
 * The embed node.
 *
 * @param {{types?: () => Array<{type: string, label: string, url: string}>}} options
 *        the types the admin may use, read when a chip is drawn
 */
export const AtriumEmbed = Node.create({
  name: 'atriumEmbed',
  group: 'block',
  atom: true,
  selectable: true,
  draggable: true,

  addOptions() {
    return { types: () => [] };
  },

  addAttributes() {
    return {
      kind: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-atrium-embed'),
        renderHTML: (attributes) => ({ 'data-atrium-embed': attributes.kind }),
      },
      id: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-id'),
        renderHTML: (attributes) => (attributes.id ? { 'data-id': attributes.id } : {}),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'div[data-atrium-embed]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes)];
  },

  addNodeView() {
    return ({ node }) => {
      const dom = document.createElement('div');
      const type = this.options.types().find((candidate) => candidate.type === node.attrs.kind);
      const label = type?.label ?? node.attrs.kind;
      const chip = (name) => {
        dom.textContent = `${label}: ${name ?? `#${node.attrs.id ?? '?'}`}`;
      };

      dom.className = 'atrium-embed';
      dom.contentEditable = 'false';
      dom.dataset.embedChip = node.attrs.kind ?? '';
      chip(names.get(key(node.attrs.kind, node.attrs.id)) ?? null);

      // A type this admin may not use has no url: nothing to ask, the id stays.
      if (type && node.attrs.id && !names.has(key(node.attrs.kind, node.attrs.id))) {
        nameOf(type, node.attrs.id).then(chip);
      }

      return { dom };
    };
  },
});

/** The extension, wired to the `embeds` shared prop of the page. */
export function embedExtension() {
  const page = usePage();

  return AtriumEmbed.configure({ types: () => page.props.embeds || [] });
}

/**
 * Put an embed at the remembered caret (see caretPosition() in editor.js):
 * the toolbar took focus, and without the position the embed would land at
 * the start of the field. No remembered position — the end of the text.
 */
export function insertEmbed(editor, { type, id }, at = null) {
  if (!editor) {
    return;
  }

  const end = editor.state.doc.content.size;

  editor
    .chain()
    .focus()
    .setTextSelection(at ?? { from: end, to: end })
    .insertContent({ type: AtriumEmbed.name, attrs: { kind: type, id: String(id) } })
    .run();
}

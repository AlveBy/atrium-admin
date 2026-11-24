/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * TipTap extension set for the admin editors.
 *
 * TipTap is not an "HTML editor" but a document editor with a strict schema:
 * anything the schema has no node for is silently dropped on load, and the
 * next save writes the truncated document. That is how tables got lost —
 * StarterKit does not include them, and a price table turned into a column
 * of values. So the extension set lives here as one list shared by both
 * fields: diverging copies already bit us once with CSRF.
 *
 * Tables in migrated content are often laid out by Word:
 * `<table border="1" cellspacing="0" cellpadding="0">`, cell alignment via
 * `style`, colspan up to 13. The TipTap schema knows none of these
 * attributes, yet the public site renders them — so they are passed through
 * as is: a round trip through the editor must not change how a table looks.
 */

// Editor content styles sit next to the extensions so the fields don't
// diverge: without these rules Tailwind resets headings and lists.
import './editor.css';

import { DOMSerializer } from '@tiptap/pm/model';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { TextAlign } from '@tiptap/extension-text-align';
import { t } from './i18n';
import { embedExtension } from './embeds';

/** An attribute kept as is — TipTap knows nothing about it. */
function passthroughAttribute(name) {
  return {
    default: null,
    parseHTML: (element) => element.getAttribute(name),
    renderHTML: (attributes) => (attributes[name] ? { [name]: attributes[name] } : {}),
  };
}

/** The extension with pass-through attributes added on top of its own. */
function keeping(extension, names) {
  return extension.extend({
    addAttributes() {
      const attributes = { ...(this.parent?.() ?? {}) };

      names.forEach((name) => {
        attributes[name] = passthroughAttribute(name);
      });

      return attributes;
    },
  });
}

// Cell layout in migrated content relies on style and colspan/rowspan
// (TipTap knows the latter itself); the rest is a legacy of Word and old templates.
const CELL_ATTRIBUTES = ['style', 'class', 'align', 'valign', 'bgcolor', 'width'];
const TABLE_ATTRIBUTES = ['style', 'class', 'border', 'cellpadding', 'cellspacing', 'width', 'align'];
const ROW_ATTRIBUTES = ['style', 'class'];

/**
 * Paragraph alignment — four options, as in the Bitrix editor.
 * The order of this list is the order of the toolbar buttons.
 * Titles are lang keys: they are translated when the toolbar is built,
 * not at import time, when the dictionary is not loaded yet.
 */
const ALIGNMENTS = [
  { value: 'left', name: 'alignLeft', title: 'fields.editor.align_left' },
  { value: 'center', name: 'alignCenter', title: 'fields.editor.align_center' },
  { value: 'right', name: 'alignRight', title: 'fields.editor.align_right' },
  { value: 'justify', name: 'alignJustify', title: 'fields.editor.align_justify' },
];

// Which nodes may be aligned. Table cells are not in the list: their style and
// align already pass through as attributes, and two attributes writing the same
// style would overwrite each other. A paragraph inside a cell is unaffected.
const ALIGNED_NODES = ['paragraph', 'heading', 'blockquote'];

/**
 * Paragraph alignment.
 *
 * Migrated content is marked up both ways at once: `style="text-align:
 * justify"` and Word's `align="center"` (the latter in price tables, inside cells).
 * The stock extension reads only style, and whatever the schema doesn't know is
 * lost on the very first save — so we read both and write style, like a browser.
 */
const Alignment = TextAlign.extend({
  addGlobalAttributes() {
    const { types, alignments } = this.options;

    return [
      {
        types,
        attributes: {
          textAlign: {
            default: null,
            // style wins over align: content contains
            // `<p align="center" style="text-align: left;">`, and the browser
            // renders such a paragraph on the left.
            parseHTML: (element) => {
              const value = element.style.textAlign || element.getAttribute('align') || '';

              return alignments.includes(value) ? value : null;
            },
            renderHTML: (attributes) =>
              attributes.textAlign ? { style: `text-align: ${attributes.textAlign}` } : {},
          },
        },
      },
    ];
  },
}).configure({ types: ALIGNED_NODES, alignments: ALIGNMENTS.map((alignment) => alignment.value) });

/**
 * @param {{placeholder?: string, extra?: Array}} options
 */
export function editorExtensions({ placeholder = t('fields.editor.placeholder'), extra = [] } = {}) {
  return [
    // link and underline are part of StarterKit; turn them off so they don't
    // clash with ours — TipTap complains in the console about duplicate names.
    StarterKit.configure({ link: false, underline: false }),
    Underline,
    Link.configure({ openOnClick: false }),
    // An image is part of the line, not a separate block. In migrated content
    // it already sits inside a paragraph (`<p align="center"><img>`), and a block
    // image got pulled out of it: the paragraph was left empty and the photo
    // stuck to the left, with no way to align it.
    Image.configure({ inline: true }),
    // Text colour, background and size: the same tables mark "sold out" in red,
    // and without TextStyle that mark would become plain text.
    TextStyleKit,
    Alignment,
    keeping(Table, TABLE_ATTRIBUTES),
    keeping(TableRow, ROW_ATTRIBUTES),
    keeping(TableHeader, CELL_ATTRIBUTES),
    keeping(TableCell, CELL_ATTRIBUTES),
    // Forms, blocks and the rest that modules offer under "Insert": a node of its
    // own, or the editor would throw the placeholders away on the first save.
    embedExtension(),
    Placeholder.configure({ placeholder }),
    ...extra,
  ];
}

/** Table buttons. Some are visible only while the cursor is inside a table. */
export function tableToolbarButtons(editor, icons) {
  const inTable = () => Boolean(editor.value?.isActive('table'));
  const chain = () => editor.value?.chain().focus();

  return [
    {
      name: 'table',
      title: t('fields.editor.table_insert'),
      icon: icons.table,
      action: () => chain()?.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    },
    {
      name: 'addRowAfter',
      title: t('fields.editor.table_add_row'),
      icon: icons.rowPlus,
      action: () => chain()?.addRowAfter().run(),
      visible: inTable,
    },
    {
      name: 'deleteRow',
      title: t('fields.editor.table_delete_row'),
      icon: icons.rowMinus,
      action: () => chain()?.deleteRow().run(),
      visible: inTable,
    },
    {
      name: 'addColumnAfter',
      title: t('fields.editor.table_add_column'),
      icon: icons.columnPlus,
      action: () => chain()?.addColumnAfter().run(),
      visible: inTable,
    },
    {
      name: 'deleteColumn',
      title: t('fields.editor.table_delete_column'),
      icon: icons.columnMinus,
      action: () => chain()?.deleteColumn().run(),
      visible: inTable,
    },
    {
      name: 'mergeOrSplit',
      title: t('fields.editor.table_merge_split'),
      icon: icons.mergeCells,
      action: () => chain()?.mergeOrSplit().run(),
      visible: inTable,
    },
    {
      name: 'deleteTable',
      title: t('fields.editor.table_delete'),
      icon: icons.tableMinus,
      action: () => chain()?.deleteTable().run(),
      visible: inTable,
    },
  ];
}

/** Paragraph alignment buttons. */
export function alignToolbarButtons(editor, icons) {
  // A paragraph without the attribute is left-aligned: highlight "left", otherwise
  // none of the four buttons looks pressed on plain text.
  const current = () =>
    ALIGNMENTS.find(({ value }) => editor.value?.isActive({ textAlign: value }))?.value ?? 'left';

  return ALIGNMENTS.map(({ value, name, title }) => ({
    name,
    title: t(title),
    icon: icons[name],
    action: () => editor.value?.chain().focus().setTextAlign(value).run(),
    isActive: () => current() === value,
  }));
}

/**
 * The caret position the user sees.
 *
 * TipTap does not learn about a mouse click right away: ProseMirror reads the
 * selection from the DOM lazily, on a timer. A click on a toolbar button takes
 * focus away from the editor before that read happens, and the editor keeps
 * thinking the cursor is at the very start of the field — an image from the
 * media library lands at the start of the text, and the page scrolls there too.
 * The miss is rare — whether the read makes it depends on how busy the browser
 * is: a long page with many photos slows it down noticeably.
 *
 * So we read the position ourselves and early — at the moment the button is
 * pressed, while focus is still in the editor. By then the DOM caret is in place.
 *
 * Returns null if this editor has no caret: the field was never entered, and
 * a "current position" simply doesn't exist in it.
 */
export function caretPosition(editor) {
  const view = editor?.view;
  const selection = typeof window === 'undefined' ? null : window.getSelection();

  if (!view || !selection || selection.rangeCount === 0) {
    return null;
  }

  const { anchorNode, anchorOffset, focusNode, focusOffset } = selection;

  if (!anchorNode || !view.dom.contains(anchorNode)) {
    return null;
  }

  try {
    const anchor = view.posAtDOM(anchorNode, anchorOffset);
    const head =
      focusNode && view.dom.contains(focusNode) ? view.posAtDOM(focusNode, focusOffset) : anchor;

    return { from: Math.min(anchor, head), to: Math.max(anchor, head) };
  } catch {
    // posAtDOM throws on nodes that are not in the document (e.g. the caret
    // stayed in a piece the editor has already re-rendered).
    return null;
  }
}

/**
 * Insert a file from the media library.
 *
 * `at` is the position remembered when the button was pressed. Without it the
 * file goes to the end of the text: the cursor was never placed in the field, and
 * the start of the text is the worst place to pick for the user (that is exactly
 * what the bug looked like). TipTap clamps the position to the document bounds
 * if the text has changed meanwhile.
 */
export function insertLibraryFile(editor, file, at = null) {
  if (!editor) {
    return;
  }

  const end = editor.state.doc.content.size;
  const chain = editor.chain().focus().setTextSelection(at ?? { from: end, to: end });

  if (file.is_image) {
    chain.setImage({ src: file.url });
  } else {
    const linkText = file.filename || file.url;

    chain.insertContent(`<a href="${file.url}" target="_blank">${linkText}</a>`);
  }

  chain.run();
}

/**
 * The piece of text picked out in the editor, for the AI to improve — and
 * where to put the improved piece back.
 *
 * A piece inside one paragraph goes as its inline HTML (a word with its bold or
 * link marks) and comes back the same way. A piece over several paragraphs
 * grows to whole top-level blocks: putting blocks into the middle of a
 * paragraph would split it, whereas whole blocks are swapped for whole blocks.
 *
 * `remembered` is the selection read when the button was pressed (see
 * caretPosition()), for when the editor's own state has already lost it.
 *
 * Returns null when nothing is picked.
 */
export function pickPiece(editor, remembered = null) {
  if (!editor) {
    return null;
  }

  let { from, to } = editor.state.selection;

  if (from === to && remembered && remembered.from !== remembered.to) {
    ({ from, to } = remembered);
  }

  if (from === to) {
    return null;
  }

  const doc = editor.state.doc;
  const size = doc.content.size;

  from = Math.max(0, Math.min(from, size));
  to = Math.max(0, Math.min(to, size));

  const $from = doc.resolve(from);
  const $to = doc.resolve(to);
  const inline = $from.sameParent($to) && $from.parent.isTextblock;

  if (!inline) {
    from = $from.before(1);
    to = $to.after(1);
  }

  const wrapper = document.createElement('div');
  wrapper.appendChild(DOMSerializer.fromSchema(editor.schema).serializeFragment(doc.slice(from, to).content));

  return { html: wrapper.innerHTML, from, to, inline };
}

/**
 * Put the improved piece where pickPiece() took it from. A model that
 * answers a word with `<p>word</p>` must not split the paragraph: the wrapper
 * of an inline piece is dropped.
 */
export function replacePiece(editor, piece, html) {
  let text = String(html ?? '').trim();

  if (piece.inline) {
    const wrapped = /^<p>([\s\S]*)<\/p>$/.exec(text);

    if (wrapped && !wrapped[1].includes('<p')) {
      text = wrapped[1];
    }
  }

  editor.chain().focus().insertContentAt({ from: piece.from, to: piece.to }, text).run();
}

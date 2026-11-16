<template>
  <div class="content-field" :data-content-field="name">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Mode Selector -->
    <div class="flex items-center gap-4 mb-2">
      <label v-for="m in availableModes" :key="m.value" class="flex items-center cursor-pointer">
        <input
          type="radio"
          :name="name + '_mode'"
          :value="m.value"
          :data-content-mode="m.value"
          v-model="currentMode"
          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
        />
        <span class="ml-1.5 text-sm text-gray-700">{{ m.label }}</span>
      </label>
    </div>

    <!-- Text Mode (simple textarea) -->
    <textarea
      v-if="currentMode === 'text'"
      v-model="value"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :rows="rows"
      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 resize-y"
      :class="{ 'border-red-500': error }"
    />

    <!-- HTML Mode (monospace textarea) -->
    <textarea
      v-else-if="currentMode === 'html'"
      v-model="value"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :rows="rows"
      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 resize-y font-mono text-sm bg-gray-50"
      :class="{ 'border-red-500': error }"
    />

    <!-- WYSIWYG Mode (TipTap) -->
    <div
      v-else-if="currentMode === 'wysiwyg'"
      class="border border-gray-300 rounded-lg overflow-hidden"
      :class="{ 'border-red-500': error, 'ring-2 ring-indigo-500': isFocused }"
    >
      <!-- mousedown, not click: the browser takes focus away from the editor on
           press, and after that the caret can't be read — see caretPosition(). -->
      <div
        class="flex flex-wrap items-center gap-1 p-2 bg-gray-50 border-b border-gray-300"
        @mousedown="rememberCaret"
      >
        <!-- Paragraph style. A list rather than a single "H" button: that is how
             headings were picked in Bitrix, and the level is visible without guessing. -->
        <select
          v-if="toolbar.includes('heading')"
          data-editor-format="true"
          class="mr-1 h-8 rounded border border-gray-300 bg-white px-2 text-sm text-gray-700"
          :value="currentFormat()"
          @change="applyFormat($event.target.value)"
          :title="$t('fields.editor.format')"
        >
          <option v-for="format in formats" :key="format.value" :value="format.value">{{ $t(format.label) }}</option>
        </select>

        <!-- Font size — in points, like in Word. Separate from the paragraph style:
             a heading sets meaning, a size only looks; users asked for both. -->
        <select
          v-if="toolbar.includes('fontSize')"
          data-editor-font-size="true"
          class="mr-1 h-8 rounded border border-gray-300 bg-white px-2 text-sm text-gray-700"
          :value="currentFontSize()"
          @change="applyFontSize($event.target.value)"
          :title="$t('fields.editor.font_size')"
        >
          <option v-for="size in fontSizes()" :key="size.value" :value="size.value">{{ size.label }}</option>
        </select>

        <!-- Text colour. Price tables migrated from Bitrix mark "sold out" with
             colour, and without this button there would be no way to set it. -->
        <label
          v-if="toolbar.includes('color')"
          class="flex h-8 cursor-pointer items-center gap-1 rounded px-1.5 hover:bg-gray-200"
          :title="$t('fields.editor.text_color')"
        >
          <span class="text-sm font-semibold text-gray-700">A</span>
          <input
            type="color"
            data-editor-color="true"
            class="h-5 w-6 cursor-pointer border-0 bg-transparent p-0"
            :value="currentColor()"
            @input="editor?.chain().focus().setColor($event.target.value).run()"
          />
        </label>

        <template v-for="button in toolbarButtons" :key="button.name">
          <button
            v-if="button.visible ? button.visible() : true"
            type="button"
            :data-editor-button="button.name"
            @click="button.action"
            class="p-2 rounded hover:bg-gray-200"
            :class="{ 'bg-gray-200': button.isActive?.() }"
            :title="button.title"
          >
            <span v-html="button.icon" class="w-4 h-4 block"></span>
          </button>
        </template>
      </div>

      <editor-content
        :editor="editor"
        class="max-w-none p-4 overflow-x-auto"
        :style="{ minHeight: minHeight + 'px' }"
      />
    </div>

    <!-- Hint -->
    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
    <!-- Error -->
    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>

    <!-- File Manager Modal -->
    <FileManager
      :show="showFileManager"
      :type="fileManagerType"
      @select="handleFileSelected"
      @close="showFileManager = false"
    />
  </div>
</template>

<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { ref, computed, watch, onUnmounted } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import {
  alignToolbarButtons,
  caretPosition,
  editorExtensions,
  insertLibraryFile,
  tableToolbarButtons,
} from '../../../Support/editor'
import FileManager from '../FileManager.vue'
import { t } from '@atrium/Support/i18n'

const props = defineProps({
  modelValue: String,
  name: String,
  label: String,
  required: Boolean,
  disabled: Boolean,
  placeholder: String,
  hint: String,
  error: String,
  defaultMode: { type: String, default: 'wysiwyg' },
  modes: { type: Array, default: () => ['text', 'html', 'wysiwyg'] },
  rows: { type: Number, default: 6 },
  // The default set matches ContentField in PHP: it almost always arrives as a
  // prop, but a ContentField rendered inside RepeatableField does not get it, and
  // there the field went without images for a long time because the lists diverged.
  toolbar: {
    type: Array,
    default: () => ['bold', 'italic', 'underline', 'strike', 'color', 'link', 'bulletList', 'orderedList', 'align', 'heading', 'fontSize', 'image', 'file', 'table'],
  },
  minHeight: { type: Number, default: 200 },
})

const emit = defineEmits(['update:modelValue'])

const modeLabels = {
  text: 'fields.content.mode_text',
  html: 'fields.content.mode_html',
  wysiwyg: 'fields.content.mode_wysiwyg'
}

const availableModes = computed(() =>
  props.modes.map(m => ({ value: m, label: modeLabels[m] ? t(modeLabels[m]) : m }))
)

// Load saved mode from localStorage or use default
const storageKey = computed(() => `content-field-mode-${props.name}`)
const currentMode = ref(localStorage.getItem(storageKey.value) || props.defaultMode)

// Save mode to localStorage
watch(currentMode, (mode) => {
  localStorage.setItem(storageKey.value, mode)
})

const value = computed({
  get: () => props.modelValue || '',
  set: (val) => emit('update:modelValue', val)
})

const isFocused = ref(false)
const showFileManager = ref(false)
const fileManagerType = ref('all')
const fileManagerCallback = ref(null)
// Where to insert a file. Remembered when the toolbar is pressed: by the time
// a file is picked, the editor has long lost focus and may not know its position.
const caret = ref(null)

function rememberCaret() {
  caret.value = caretPosition(editor.value)
}

function openFileManager(type = 'all', callback = null) {
  fileManagerType.value = type
  fileManagerCallback.value = callback
  showFileManager.value = true
}

function handleFileSelected(file) {
  if (fileManagerCallback.value) {
    fileManagerCallback.value(file)
    fileManagerCallback.value = null
    return
  }

  insertLibraryFile(editor.value, file, caret.value)
}

// TipTap editor
const editor = useEditor({
  content: props.modelValue || '',
  extensions: editorExtensions({ placeholder: props.placeholder || t('fields.editor.placeholder') }),
  onUpdate: ({ editor }) => {
    if (currentMode.value === 'wysiwyg') {
      emit('update:modelValue', editor.getHTML())
    }
  },
  onFocus: () => { isFocused.value = true },
  onBlur: () => { isFocused.value = false },
})

// Sync editor content when switching to wysiwyg mode.
// The second argument is options: in TipTap 2 it was an "emit update" flag, and a
// bare false meant "silently". In v3 it is an object, and a bare false reads as
// "no options" — i.e. the opposite, with the update emitted.
watch(currentMode, (mode) => {
  if (mode === 'wysiwyg' && editor.value) {
    editor.value.commands.setContent(props.modelValue || '', { emitUpdate: false })
  }
})

// Sync external value changes to editor
watch(() => props.modelValue, (val) => {
  if (currentMode.value === 'wysiwyg' && editor.value && val !== editor.value.getHTML()) {
    editor.value.commands.setContent(val || '', { emitUpdate: false })
  }
})

// Paragraph styles — as in the "Style" list of the Bitrix editor.
// Labels are lang keys, translated in the template.
const formats = [
  { value: 'paragraph', label: 'fields.editor.format_paragraph' },
  { value: 'h1', label: 'fields.editor.format_h1' },
  { value: 'h2', label: 'fields.editor.format_h2' },
  { value: 'h3', label: 'fields.editor.format_h3' },
  { value: 'h4', label: 'fields.editor.format_h4' },
  { value: 'h5', label: 'fields.editor.format_h5' },
  { value: 'h6', label: 'fields.editor.format_h6' },
  { value: 'blockquote', label: 'fields.editor.format_blockquote' },
]

// Functions, not computed: isActive() and getAttributes() are not reactive, but
// @tiptap/vue-3 re-renders the component on every transaction — so a call from
// the template returns the state at the current cursor position.
function currentFormat() {
  if (!editor.value) return 'paragraph'

  if (editor.value.isActive('blockquote')) return 'blockquote'

  for (let level = 1; level <= 6; level++) {
    if (editor.value.isActive('heading', { level })) return `h${level}`
  }

  return 'paragraph'
}

function applyFormat(format) {
  if (!editor.value) return

  const chain = editor.value.chain().focus()

  // A quote wraps a paragraph, a heading is the paragraph itself: remove the
  // wrapper separately, otherwise "Heading 2" inside a quote would stay a quote.
  if (editor.value.isActive('blockquote') && format !== 'blockquote') {
    chain.unsetBlockquote()
  }

  if (format === 'blockquote') {
    chain.setParagraph().setBlockquote().run()
    return
  }

  if (format === 'paragraph') {
    chain.setParagraph().run()
    return
  }

  chain.setHeading({ level: Number(format.slice(1)) }).run()
}

function currentColor() {
  return editor.value?.getAttributes('textStyle')?.color || '#000000'
}

// Font sizes — Word's set, in points: that is what users compare with.
// Points rather than pixels also because "12 pt" is familiar as normal text,
// while "16 px" means nothing to most people.
const FONT_SIZES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 36, 48, 72]

/**
 * What to show in the size list.
 *
 * Text migrated from Bitrix is marked up in pixels (`font-size: 20px`, e.g. on
 * sub-headings). Such a size has no entry of its own in the list, and the select
 * would show the first option — that is, lie. So if the current value is foreign,
 * it is added to the list as is — let it show what is really there.
 */
function fontSizes() {
  const sizes = [
    { value: '', label: t('fields.editor.font_size_default') },
    ...FONT_SIZES.map((size) => ({ value: `${size}pt`, label: t('fields.editor.font_size_pt', { size }) })),
  ]
  const current = currentFontSize()

  if (current && !sizes.some((size) => size.value === current)) {
    sizes.push({ value: current, label: current })
  }

  return sizes
}

function currentFontSize() {
  return editor.value?.getAttributes('textStyle')?.fontSize || ''
}

function applyFontSize(size) {
  if (!editor.value) return

  const chain = editor.value.chain().focus()

  // The empty option means "as usual": remove the size instead of writing zero.
  if (size) {
    chain.setFontSize(size).run()
  } else {
    chain.unsetFontSize().run()
  }
}

// SVG icons for toolbar
const icons = {
  bold: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>',
  italic: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>',
  underline: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6a6 6 0 0 0 12 0V4"></path><line x1="4" y1="20" x2="20" y2="20"></line></svg>',
  strike: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.3 4.9c-2.3-.6-4.4-1-6.2-.9-2.7 0-5.3.7-5.3 3.6 0 1.5 1.8 3.3 3.6 3.9h.2m8.3 3.2c0 3.6-3.5 4.4-6.8 4.4-1.5 0-3.6-.2-5.6-.8"></path><line x1="4" y1="12" x2="20" y2="12"></line></svg>',
  link: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>',
  bulletList: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
  orderedList: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>',
  image: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
  file: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
  alignLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="10" x2="17" y2="10"></line><line x1="3" y1="14" x2="21" y2="14"></line><line x1="3" y1="18" x2="17" y2="18"></line></svg>',
  alignCenter: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="6" y1="10" x2="18" y2="10"></line><line x1="3" y1="14" x2="21" y2="14"></line><line x1="6" y1="18" x2="18" y2="18"></line></svg>',
  alignRight: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="7" y1="10" x2="21" y2="10"></line><line x1="3" y1="14" x2="21" y2="14"></line><line x1="7" y1="18" x2="21" y2="18"></line></svg>',
  alignJustify: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="3" y1="14" x2="21" y2="14"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>',
  table: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="1"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>',
  rowPlus: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="1"></rect><line x1="12" y1="14" x2="12" y2="20"></line><line x1="9" y1="17" x2="15" y2="17"></line></svg>',
  rowMinus: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="1"></rect><line x1="9" y1="17" x2="15" y2="17"></line></svg>',
  columnPlus: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="6" height="18" rx="1"></rect><line x1="17" y1="9" x2="17" y2="15"></line><line x1="14" y1="12" x2="20" y2="12"></line></svg>',
  columnMinus: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="6" height="18" rx="1"></rect><line x1="14" y1="12" x2="20" y2="12"></line></svg>',
  mergeCells: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="1"></rect><line x1="12" y1="5" x2="12" y2="9"></line><line x1="12" y1="15" x2="12" y2="19"></line></svg>',
  tableMinus: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="1"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="14" y1="16" x2="20" y2="16"></line></svg>',
}

// Toolbar buttons
const toolbarButtons = computed(() => {
  const buttons = []

  if (props.toolbar.includes('bold')) {
    buttons.push({
      name: 'bold',
      title: t('fields.editor.bold_shortcut'),
      icon: icons.bold,
      action: () => editor.value?.chain().focus().toggleBold().run(),
      isActive: () => editor.value?.isActive('bold'),
    })
  }

  if (props.toolbar.includes('italic')) {
    buttons.push({
      name: 'italic',
      title: t('fields.editor.italic_shortcut'),
      icon: icons.italic,
      action: () => editor.value?.chain().focus().toggleItalic().run(),
      isActive: () => editor.value?.isActive('italic'),
    })
  }

  if (props.toolbar.includes('underline')) {
    buttons.push({
      name: 'underline',
      title: t('fields.editor.underline_shortcut'),
      icon: icons.underline,
      action: () => editor.value?.chain().focus().toggleUnderline().run(),
      isActive: () => editor.value?.isActive('underline'),
    })
  }

  if (props.toolbar.includes('strike')) {
    buttons.push({
      name: 'strike',
      title: t('fields.editor.strike'),
      icon: icons.strike,
      action: () => editor.value?.chain().focus().toggleStrike().run(),
      isActive: () => editor.value?.isActive('strike'),
    })
  }

  if (props.toolbar.includes('link')) {
    buttons.push({
      name: 'link',
      title: t('fields.editor.link'),
      icon: icons.link,
      action: () => {
        const url = prompt(t('fields.editor.link_prompt'))
        if (url) {
          editor.value?.chain().focus().setLink({ href: url }).run()
        }
      },
      isActive: () => editor.value?.isActive('link'),
    })
  }

  if (props.toolbar.includes('bulletList')) {
    buttons.push({
      name: 'bulletList',
      title: t('fields.editor.bullet_list'),
      icon: icons.bulletList,
      action: () => editor.value?.chain().focus().toggleBulletList().run(),
      isActive: () => editor.value?.isActive('bulletList'),
    })
  }

  if (props.toolbar.includes('orderedList')) {
    buttons.push({
      name: 'orderedList',
      title: t('fields.editor.ordered_list'),
      icon: icons.orderedList,
      action: () => editor.value?.chain().focus().toggleOrderedList().run(),
      isActive: () => editor.value?.isActive('orderedList'),
    })
  }

  if (props.toolbar.includes('align')) {
    buttons.push(...alignToolbarButtons(editor, icons))
  }

  // 'heading' is drawn as the style list left of the buttons — a single "H"
  // button only gave level 2, and users asked to choose the heading level.

  if (props.toolbar.includes('image')) {
    buttons.push({
      name: 'image',
      title: t('fields.editor.image'),
      icon: icons.image,
      action: () => openFileManager('image'),
    })
  }

  if (props.toolbar.includes('file')) {
    buttons.push({
      name: 'file',
      title: t('fields.editor.file'),
      icon: icons.file,
      action: () => openFileManager('all'),
    })
  }

  if (props.toolbar.includes('table')) {
    buttons.push(...tableToolbarButtons(editor, icons))
  }

  return buttons
})

onUnmounted(() => {
  editor.value?.destroy()
})
</script>

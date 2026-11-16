<template>
  <div class="wysiwyg-field">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div
      class="border border-gray-300 rounded-lg overflow-hidden"
      :class="{ 'border-red-500': error, 'ring-2 ring-indigo-500': isFocused }"
    >
      <!-- mousedown, not click: the browser takes focus away from the editor on
           press, and after that the caret can't be read — see caretPosition(). -->
      <div class="flex flex-wrap gap-1 p-2 bg-gray-50 border-b border-gray-300" @mousedown="rememberCaret">
        <button
          v-for="button in toolbarButtons"
          :key="button.name"
          type="button"
          @click="button.action"
          class="p-2 rounded hover:bg-gray-200"
          :class="{ 'bg-gray-200': button.isActive?.() }"
          :title="button.title"
        >
          <component :is="button.icon" class="h-4 w-4" />
        </button>
      </div>

      <editor-content
        :editor="editor"
        class="max-w-none p-4"
        :style="{ minHeight: minHeight + 'px', maxHeight: maxHeight ? maxHeight + 'px' : 'auto' }"
      />

      <div v-if="maxLength" class="flex justify-end p-2 bg-gray-50 border-t border-gray-300 text-sm text-gray-500">
        {{ characterCount }} / {{ maxLength }}
      </div>
    </div>

    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
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
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import CharacterCount from '@tiptap/extension-character-count';
import { caretPosition, editorExtensions, insertLibraryFile } from '../../../Support/editor';
import FileManager from '../FileManager.vue';
import { t } from '@atrium/Support/i18n';

const props = defineProps({
  modelValue: String,
  label: String,
  required: Boolean,
  toolbar: { type: Array, default: () => ['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'heading'] },
  minHeight: { type: Number, default: 200 },
  maxHeight: Number,
  maxLength: Number,
  allowImages: { type: Boolean, default: true },
  placeholder: String,
  hint: String,
  error: String,
});

const emit = defineEmits(['update:modelValue']);

const isFocused = ref(false);
const showFileManager = ref(false);
const fileManagerType = ref('all');
// Where to insert a file. Remembered when the toolbar is pressed: by the time
// a file is picked, the editor has long lost focus and may not know its position.
const caret = ref(null);

function rememberCaret() {
  caret.value = caretPosition(editor.value);
}

function openFileManager(type = 'all') {
  fileManagerType.value = type;
  showFileManager.value = true;
}

function handleFileSelected(file) {
  insertLibraryFile(editor.value, file, caret.value);
}

const editor = useEditor({
  content: props.modelValue,
  // This toolbar has no table buttons, but the schema knows tables: otherwise the
  // field would drop them from existing content. The set is shared with ContentField.
  extensions: editorExtensions({
    placeholder: props.placeholder || t('fields.editor.placeholder'),
    extra: [CharacterCount.configure({ limit: props.maxLength })],
  }),
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML());
  },
  onFocus: () => {
    isFocused.value = true;
  },
  onBlur: () => {
    isFocused.value = false;
  },
});

const characterCount = computed(() => editor.value?.storage.characterCount.characters() || 0);

const toolbarButtons = computed(() => {
  const buttons = [];

  if (props.toolbar.includes('bold')) {
    buttons.push({
      name: 'bold',
      title: t('fields.editor.bold'),
      icon: 'IconBold',
      action: () => editor.value?.chain().focus().toggleBold().run(),
      isActive: () => editor.value?.isActive('bold'),
    });
  }

  if (props.toolbar.includes('italic')) {
    buttons.push({
      name: 'italic',
      title: t('fields.editor.italic'),
      icon: 'IconItalic',
      action: () => editor.value?.chain().focus().toggleItalic().run(),
      isActive: () => editor.value?.isActive('italic'),
    });
  }

  if (props.toolbar.includes('underline')) {
    buttons.push({
      name: 'underline',
      title: t('fields.editor.underline'),
      icon: 'IconUnderline',
      action: () => editor.value?.chain().focus().toggleUnderline().run(),
      isActive: () => editor.value?.isActive('underline'),
    });
  }

  if (props.toolbar.includes('link')) {
    buttons.push({
      name: 'link',
      title: t('fields.editor.link'),
      icon: 'IconLink',
      action: () => {
        const url = prompt(t('fields.editor.link_prompt'));
        if (url) {
          editor.value?.chain().focus().setLink({ href: url }).run();
        }
      },
      isActive: () => editor.value?.isActive('link'),
    });
  }

  if (props.toolbar.includes('bulletList')) {
    buttons.push({
      name: 'bulletList',
      title: t('fields.editor.bullet_list'),
      icon: 'IconList',
      action: () => editor.value?.chain().focus().toggleBulletList().run(),
      isActive: () => editor.value?.isActive('bulletList'),
    });
  }

  if (props.toolbar.includes('orderedList')) {
    buttons.push({
      name: 'orderedList',
      title: t('fields.editor.ordered_list'),
      icon: 'IconListOrdered',
      action: () => editor.value?.chain().focus().toggleOrderedList().run(),
      isActive: () => editor.value?.isActive('orderedList'),
    });
  }

  if (props.toolbar.includes('heading')) {
    buttons.push({
      name: 'heading',
      title: t('fields.editor.heading'),
      icon: 'IconHeading',
      action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
      isActive: () => editor.value?.isActive('heading'),
    });
  }

  if (props.toolbar.includes('image') && props.allowImages) {
    buttons.push({
      name: 'image',
      title: t('fields.editor.image'),
      icon: 'IconImage',
      action: () => openFileManager('image'),
    });
  }

  if (props.toolbar.includes('file')) {
    buttons.push({
      name: 'file',
      title: t('fields.editor.file'),
      icon: 'IconFile',
      action: () => openFileManager('all'),
    });
  }

  return buttons;
});

// The second argument is options: in TipTap 2 it was an "emit update" flag, and a
// bare false meant "silently". In v3 it is an object, and a bare false reads as
// "no options" — i.e. the opposite, with the update emitted.
watch(() => props.modelValue, (value) => {
  if (editor.value && value !== editor.value.getHTML()) {
    editor.value.commands.setContent(value, { emitUpdate: false });
  }
});

onUnmounted(() => {
  editor.value?.destroy();
});
</script>

<template>
  <div class="gallery-field">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div
      class="grid grid-cols-4 gap-4"
      @dragover.prevent
      @drop="handleDrop"
    >
      <div
        v-for="(image, index) in imageViews"
        :key="image.src || index"
        class="relative group aspect-square bg-gray-100 rounded-lg overflow-hidden"
        draggable="true"
        @dragstart="handleDragStart(index)"
        @dragover.prevent
        @drop="handleReorder(index)"
      >
        <!-- Свойство «Документы» в Битриксе — тот же множественный файл, что и
             галерея, но с расширениями doc/pdf/xls. Рисовать их через <img>
             значит показывать битую картинку вместо файла, поэтому неизображения
             получают плитку с иконкой и именем файла. -->
        <img
          v-if="isImage(image)"
          :src="image.src"
          :alt="image.filename"
          class="w-full h-full object-cover"
        />
        <a
          v-else
          :href="image.src"
          target="_blank"
          rel="noopener"
          class="w-full h-full flex flex-col items-center justify-center p-2 text-center hover:bg-gray-200"
          :title="image.filename"
        >
          <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          <span class="mt-1 text-xs text-gray-600 break-all line-clamp-2">{{ image.filename }}</span>
        </a>

        <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
          <button
            type="button"
            @click="removeImage(index)"
            class="p-2 bg-red-500 text-white rounded-full hover:bg-red-600"
          >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>

        <div
          v-if="sortable"
          class="absolute top-2 left-2 bg-white bg-opacity-75 rounded px-2 py-1 text-xs font-medium cursor-move"
        >
          {{ index + 1 }}
        </div>
      </div>

      <div
        v-if="canAddMore"
        class="aspect-square border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center hover:border-indigo-500 hover:bg-indigo-50 cursor-pointer transition-colors"
        @click="triggerUpload"
      >
        <div class="text-center">
          <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span class="mt-1 block text-sm text-gray-500">Добавить</span>
        </div>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      multiple
      class="hidden"
      @change="handleFileSelect"
    />

    <button
      v-if="canAddMore"
      type="button"
      @click="showLibrary = true"
      data-action="pick-from-library"
      class="mt-3 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
    >
      Добавить из библиотеки
    </button>

    <FileManager
      :show="showLibrary"
      type="image"
      @select="pickFromLibrary"
      @close="showLibrary = false"
    />

    <p v-if="hint && !error" class="mt-2 text-sm text-gray-500">{{ hint }}</p>
    <p v-if="error" class="mt-2 text-sm text-red-500">{{ error }}</p>
    <p class="mt-2 text-sm text-gray-400">
      {{ images.length }} / {{ maxFiles }} изображений
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import FileManager from '../FileManager.vue';
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  label: String,
  required: Boolean,
  maxFiles: { type: Number, default: 20 },
  maxSize: { type: Number, default: 5120 },
  accept: { type: String, default: 'image/*' },
  sortable: { type: Boolean, default: true },
  hint: String,
  error: String,
  uploadUrl: { type: String, default: '/admin/upload' },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const dragIndex = ref(null);

const images = computed(() => props.modelValue || []);

// Values reach this component in two shapes: plain path strings (how the Bitrix
// migration stored them, and what we now write) and, historically, the whole
// upload response object. Render both, and never emit the object form again —
// mixing shapes inside one json column is what broke the img src.
function entrySrc(entry) {
  const raw = typeof entry === 'string' ? entry : (entry?.url ?? entry?.path ?? '');
  if (typeof raw !== 'string' || raw === '') return '';
  if (/^(https?:)?\/\//i.test(raw)) return raw;
  if (raw.startsWith('/')) return raw;
  return '/storage/' + raw;
}

function entryName(entry) {
  if (entry && typeof entry === 'object' && entry.filename) return entry.filename;
  const src = entrySrc(entry);
  return src.split('/').filter(Boolean).pop() || '';
}

// Картинка или документ — решаем по расширению: одно и то же поле в Битриксе
// принимает и то, и другое (ФОТОГАЛЕРЕЯ vs ДОКУМЕНТЫ).
function isImage(view) {
  return /\.(jpe?g|png|gif|webp|bmp|svg|avif)(\?|$)/i.test(view.src || '');
}

const imageViews = computed(() =>
  images.value.map((entry) => ({ src: entrySrc(entry), filename: entryName(entry) }))
);

// Host-independent value to persist. The response `url` embeds APP_URL, which
// is not the host the browser is on, so storing it would bake an unroutable
// hostname into the database.
function storedValueFor(result) {
  if (result && typeof result.path === 'string' && result.path !== '') {
    return '/storage/' + result.path.replace(/^\/+/, '');
  }
  return entrySrc(result);
}

const canAddMore = computed(() => images.value.length < props.maxFiles);

// Медиабиблиотека: добавить в галерею уже загруженную картинку.
const showLibrary = ref(false);

function pickFromLibrary(file) {
  showLibrary.value = false;

  const url = file?.url;

  if (!url || !canAddMore.value) {
    return;
  }

  // Одна и та же картинка в галерее дважды — почти наверняка промах мышью.
  if (images.value.some((entry) => entrySrc(entry) === url)) {
    return;
  }

  emit('update:modelValue', [...images.value, url]);
}

function triggerUpload() {
  fileInput.value?.click();
}

async function handleFileSelect(event) {
  const files = Array.from(event.target.files);

  for (const file of files) {
    if (!canAddMore.value) break;

    if (file.size > props.maxSize * 1024) {
      alert(`Файл ${file.name} превышает максимальный размер ${props.maxSize} КБ`);
      continue;
    }

    await uploadFile(file);
  }

  event.target.value = '';
}

async function uploadFile(file) {
  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await fetch(props.uploadUrl, {
      method: 'POST',
      body: formData,
      headers: ajaxHeaders(),
    });

    if (!response.ok) {
      throw new Error(await responseErrorMessage(response, `Не удалось загрузить файл ${file.name}`));
    }

    const result = await response.json();

    emit('update:modelValue', [...images.value, storedValueFor(result)]);
  } catch (error) {
    console.error('Upload error:', error);
    alert(error.message || 'Ошибка загрузки файла');
  }
}

function handleDrop(event) {
  const files = Array.from(event.dataTransfer.files);

  files.forEach(file => {
    if (canAddMore.value && file.type.startsWith('image/')) {
      uploadFile(file);
    }
  });
}

function removeImage(index) {
  const newImages = [...images.value];
  newImages.splice(index, 1);
  emit('update:modelValue', newImages);
}

function handleDragStart(index) {
  dragIndex.value = index;
}

function handleReorder(targetIndex) {
  if (dragIndex.value === null || dragIndex.value === targetIndex) return;

  const newImages = [...images.value];
  const [removed] = newImages.splice(dragIndex.value, 1);
  newImages.splice(targetIndex, 0, removed);

  emit('update:modelValue', newImages);
  dragIndex.value = null;
}
</script>

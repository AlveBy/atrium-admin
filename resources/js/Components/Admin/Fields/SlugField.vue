<template>
  <div class="slug-field">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="flex gap-2">
      <div class="relative flex-1">
        <input
          type="text"
          :value="modelValue"
          @input="handleInput"
          @blur="handleBlur"
          class="w-full border border-gray-300 rounded-md px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
          :class="{ 'border-red-500': error }"
          :placeholder="placeholder || 'url-slug'"
          :disabled="disabled"
        />

        <button
          v-if="autoGenerate && sourceValue"
          type="button"
          @click="generateSlug"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-500"
          title="Сгенерировать из названия"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>

    <div v-if="showPreview && modelValue" class="mt-2 text-sm text-gray-500">
      <span class="font-medium">URL:</span>
      <a
        :href="previewUrl"
        target="_blank"
        class="text-indigo-600 hover:text-indigo-800 break-all"
      >
        {{ previewUrl }}
      </a>
    </div>

    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue';

const props = defineProps({
  modelValue: String,
  label: String,
  required: Boolean,
  fromField: { type: String, default: 'name' },
  autoGenerate: { type: Boolean, default: true },
  showPreview: { type: Boolean, default: true },
  baseUrl: String,
  // Адрес сайта, а не путь ЧПУ админки: префикс роута («countries»),
  // поле формы с разделом и путь каждого раздела дерева. Кладёт контроллер
  // по карте atrium.public_urls.
  publicPrefix: String,
  parentField: String,
  parentPaths: { type: Object, default: () => ({}) },
  placeholder: String,
  disabled: Boolean,
  hint: String,
  error: String,
});

const emit = defineEmits(['update:modelValue']);

// Get form data from parent (inject from form component)
const formData = inject('formData', {});

const sourceValue = computed(() => formData.value?.[props.fromField] || '');

/**
 * Ведём ли код за названием.
 *
 * У нового элемента — да, пока в код не вписали что-то своё: в Битриксе
 * символьный код так же подставлялся сам по мере набора названия. У уже
 * сохранённого элемента код есть, и трогать его нельзя — это адрес живой
 * страницы, он поменяется только руками.
 */
const followsName = ref(!props.modelValue);

/**
 * Путь выбранного раздела: «china», «china/wellness-tours».
 *
 * Считается по полю формы, а не по сохранённому элементу, — иначе адрес
 * врал бы ровно там, где на него смотрят: пока раздел выбирают.
 */
const parentPath = computed(() => {
  if (!props.parentField) return '';

  const parentId = formData.value?.[props.parentField];
  if (!parentId) return '';

  return props.parentPaths?.[parentId] || '';
});

const previewUrl = computed(() => {
  const base = (props.baseUrl || window.location.origin).replace(/\/+$/, '');
  const path = [props.publicPrefix, parentPath.value, props.modelValue]
    .filter(Boolean)
    .join('/');

  return `${base}/${path}`;
});

function handleInput(event) {
  const input = event.target;
  const caret = input.selectionStart;
  // Пока печатают, края не трогаем: обрезав дефис в конце, мы не дали бы
  // набрать «два-слова» — дефис исчезал бы сразу после нажатия.
  const value = sanitize(input.value);

  // Пустое поле снова отдаём названию: так его чинят, если код испортили.
  followsName.value = value === '';

  // Поле принимает не всё, что напечатали. Если после чистки строка
  // изменилась, а значение осталось прежним, Vue не перерисует input — и в
  // нём останется висеть текст, которого в значении нет.
  if (input.value !== value) {
    input.value = value;
    input.setSelectionRange(caret, caret);
  }

  emit('update:modelValue', value);
}

function handleBlur(event) {
  const value = trimDashes(props.modelValue || '');

  if (event.target.value !== value) {
    event.target.value = value;
  }

  if (value !== props.modelValue) {
    emit('update:modelValue', value);
  }
}

function generateSlug() {
  if (sourceValue.value) {
    followsName.value = true;
    emit('update:modelValue', slugify(sourceValue.value));
  }
}

/** Чистка на лету: латиница, цифры и дефис — больше в адресе ничего не живёт. */
function sanitize(text) {
  return transliterate(text)
    .toLowerCase()
    .replace(/[^a-z0-9\-]/g, '-')
    .replace(/-+/g, '-');
}

function trimDashes(text) {
  return text.replace(/^-+|-+$/g, '');
}

function slugify(text) {
  return trimDashes(sanitize(text));
}

function transliterate(text) {
  const map = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo',
    'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
    'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '',
    'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
    // Беларуская мова: сайт про туры по Беларуси, «і» и «ў» в названиях
    // обычное дело, а без них они превращались в дефисы.
    'і': 'i', 'ў': 'u', 'ґ': 'g', 'є': 'ie', 'ї': 'i', '\u2019': '', "'": '',
  };

  return text
    .toLowerCase()
    .split('')
    .map(char => map[char] ?? char)
    .join('');
}

// Пока код не трогали руками, он идёт за названием целиком — а не только по
// первой букве, как раньше: старая проверка `!props.modelValue` срабатывала
// один раз, на первом же набранном символе, и после этого код застывал.
watch(sourceValue, (newValue) => {
  if (props.autoGenerate && followsName.value && newValue) {
    emit('update:modelValue', slugify(newValue));
  }
});
</script>

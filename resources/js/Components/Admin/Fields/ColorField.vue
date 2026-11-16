<template>
  <div class="color-field">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="flex items-center gap-3">
      <div
        class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer"
        :style="{ backgroundColor: modelValue || '#ffffff' }"
        @click="togglePicker"
      ></div>

      <input
        v-if="allowCustom"
        type="text"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        :class="{ 'border-red-500': error }"
        placeholder="#000000"
      />

      <input
        ref="colorPicker"
        type="color"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="sr-only"
      />
    </div>

    <div v-if="presetColors.length" class="flex flex-wrap gap-2 mt-3">
      <button
        v-for="color in presetColors"
        :key="color"
        type="button"
        class="w-8 h-8 rounded-lg border-2 transition-all"
        :class="modelValue === color ? 'border-indigo-500 scale-110' : 'border-transparent hover:scale-105'"
        :style="{ backgroundColor: color }"
        @click="$emit('update:modelValue', color)"
      ></button>
    </div>

    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  modelValue: String,
  label: String,
  required: Boolean,
  presetColors: { type: Array, default: () => [] },
  allowCustom: { type: Boolean, default: true },
  hint: String,
  error: String,
});

const emit = defineEmits(['update:modelValue']);

const colorPicker = ref(null);

function togglePicker() {
  colorPicker.value?.click();
}
</script>

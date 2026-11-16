<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'

const props = defineProps({
    name: {
        type: String,
        required: true
    },
    label: {
        type: String,
        default: ''
    },
    modelValue: {
        type: Array,
        default: () => []
    },
    options: {
        type: Array,
        default: () => []
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    placeholder: {
        type: String,
        default: null
    },
    error: {
        type: String,
        default: null
    },
    hint: {
        type: String,
        default: null
    },
    multiple: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:modelValue'])

// Value with v-model support (ensure array)
const value = computed({
    get: () => {
        if (!props.modelValue) return []
        return Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
    },
    set: (val) => emit('update:modelValue', val || [])
})

// Check if option is selected (compare as strings to handle type mismatch)
const isSelected = (optionValue) => {
    return value.value.map(String).includes(String(optionValue))
}

// Toggle option selection (compare as strings to handle type mismatch)
const toggleOption = (optionValue) => {
    if (props.disabled) return

    const current = [...value.value]
    const index = current.findIndex(v => String(v) === String(optionValue))

    if (index === -1) {
        current.push(optionValue)
    } else {
        current.splice(index, 1)
    }

    emit('update:modelValue', current)
}

// Get selected labels for display (compare as strings)
const selectedLabels = computed(() => {
    return value.value
        .map(val => props.options.find(opt => String(opt.value) === String(val))?.label)
        .filter(Boolean)
})

// Clear all selections
const clearAll = () => {
    if (!props.disabled) {
        emit('update:modelValue', [])
    }
}
</script>

<template>
    <div class="belongs-to-many-field">
        <!-- Label -->
        <label
            :for="name"
            class="block text-sm font-medium text-fg mb-1"
        >
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <!-- Selected items display -->
        <div v-if="value.length > 0" class="mb-2 flex flex-wrap gap-1">
            <span
                v-for="(label, index) in selectedLabels"
                :key="index"
                class="inline-flex items-center px-2 py-1 rounded-md text-sm bg-accent-soft text-accent-hover"
            >
                {{ label }}
                <button
                    type="button"
                    @click="toggleOption(value[index])"
                    class="ml-1 text-accent hover:text-accent-hover"
                    :disabled="disabled"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <button
                v-if="value.length > 1"
                type="button"
                @click="clearAll"
                class="text-xs text-muted hover:text-danger"
                :disabled="disabled"
            >
                {{ $t('fields.belongs_to_many.clear_all') }}
            </button>
        </div>

        <!-- Options list -->
        <div
            v-if="options.length > 0"
            class="border border-field rounded-md max-h-64 overflow-y-auto"
            :class="{ 'border-danger': error, 'opacity-50': disabled }"
        >
            <label
                v-for="option in options"
                :key="option.value"
                :data-option-value="option.value"
                :data-option-selected="isSelected(option.value) ? 'true' : 'false'"
                class="flex items-center px-3 py-2 hover:bg-hover cursor-pointer border-b border-line last:border-b-0"
                :class="{ 'bg-accent-soft': isSelected(option.value) }"
            >
                <input
                    type="checkbox"
                    :checked="isSelected(option.value)"
                    @change="toggleOption(option.value)"
                    :disabled="disabled"
                    class="h-4 w-4 text-accent focus:ring-focus border-field rounded"
                />
                <span class="ml-2 text-sm text-fg">{{ option.label }}</span>
            </label>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="border border-field rounded-md px-3 py-4 text-center text-muted text-sm"
        >
            {{ $t('fields.belongs_to_many.no_options') }}
        </div>

        <!-- Hint -->
        <p v-if="hint && !error" class="mt-1 text-sm text-muted">
            {{ hint }}
        </p>

        <!-- Error -->
        <p v-if="error" class="mt-1 text-sm text-danger">
            {{ error }}
        </p>
    </div>
</template>

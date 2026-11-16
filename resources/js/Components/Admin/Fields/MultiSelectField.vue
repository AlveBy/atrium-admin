<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import VueMultiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

// Several values from a fixed list: chips in the box, a searchable list
// below it. The value stays a plain array of option values.
const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [Array, String, Number], default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const values = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return []
    return (Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]).map(String)
})

// Values may come back as numbers or strings: compare them as strings.
const selected = computed({
    get: () => values.value
        .map(v => props.options.find(option => String(option.value) === v))
        .filter(Boolean),
    set: (options) => emit('update:modelValue', options.map(option => option.value)),
})

const isSelected = (option) => values.value.includes(String(option.value))
</script>

<template>
    <VueMultiselect
        v-model="selected"
        :id="id"
        :options="options"
        track-by="value"
        label="label"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :show-labels="false"
        :placeholder="placeholder"
        :disabled="disabled"
        class="atrium-multiselect"
        :class="{ 'atrium-multiselect--error': error }"
        :data-multiselect="id"
    >
        <template #tag="{ option, remove }">
            <span class="atrium-multiselect__tag" :data-selected-value="option.value">
                {{ option.label }}
                <button
                    type="button"
                    class="atrium-multiselect__remove"
                    :aria-label="$t('common.delete') + ': ' + option.label"
                    :disabled="disabled"
                    @mousedown.prevent.stop="remove(option)"
                    @keydown.enter.prevent="remove(option)"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </template>

        <template #option="{ option }">
            <span
                class="atrium-multiselect__option"
                :data-option-value="option.value"
                :data-option-selected="isSelected(option) ? 'true' : 'false'"
            >
                <!-- Selection is shown by the check mark, not only by colour. -->
                <svg class="atrium-multiselect__check" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path v-if="isSelected(option)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                {{ option.label }}
            </span>
        </template>

        <template #noResult>{{ $t('fields.multi_select.nothing_found') }}</template>
        <template #noOptions>{{ $t('fields.belongs_to_many.no_options') }}</template>
    </VueMultiselect>
</template>

<style>
/* Colours follow the other inputs; contrast is in the commit message. */
.atrium-multiselect { color: #101828; font-size: 0.875rem; min-height: 42px; }
.atrium-multiselect .multiselect__tags {
    border: 1px solid #d1d5dc;
    border-radius: 0.375rem;
    min-height: 42px;
    padding: 7px 40px 0 8px;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}
.atrium-multiselect--error .multiselect__tags { border-color: #c10007; }
.atrium-multiselect.multiselect--active .multiselect__tags {
    border-color: #155dfc;
    box-shadow: 0 0 0 1px #155dfc;
}
.atrium-multiselect .multiselect__placeholder { color: #4a5565; font-size: 0.875rem; padding-top: 1px; margin-bottom: 7px; }
.atrium-multiselect .multiselect__input,
.atrium-multiselect .multiselect__single { font-size: 0.875rem; color: #101828; }
.atrium-multiselect .multiselect__select { height: 40px; }
.atrium-multiselect .multiselect__select::before { border-color: #4a5565 transparent transparent; }
.atrium-multiselect .multiselect__content-wrapper { border-color: #d1d5dc; border-radius: 0 0 0.375rem 0.375rem; }

.atrium-multiselect__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0 0.25rem 6px 0;
    padding: 2px 4px 2px 8px;
    border-radius: 0.375rem;
    background: #dbeafe;
    color: #193cb8;
    line-height: 1.25rem;
}
.atrium-multiselect__remove {
    display: inline-flex;
    padding: 2px;
    border-radius: 0.25rem;
    color: #193cb8;
}
.atrium-multiselect__remove:hover,
.atrium-multiselect__remove:focus-visible { background: #193cb8; color: #ffffff; outline: none; }

.atrium-multiselect .multiselect__option { padding: 8px 12px; min-height: 0; line-height: 1.25rem; font-size: 0.875rem; color: #101828; }
.atrium-multiselect .multiselect__option--highlight,
.atrium-multiselect .multiselect__option--selected.multiselect__option--highlight { background: #dbeafe; color: #101828; }
.atrium-multiselect .multiselect__option--selected { background: #ffffff; color: #101828; font-weight: 600; }
.atrium-multiselect__option { display: inline-flex; align-items: center; gap: 0.5rem; }
.atrium-multiselect__check { width: 1rem; height: 1rem; flex-shrink: 0; color: #193cb8; }
</style>

<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, inject, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Workspace from '../Builder/Workspace.vue'
import { blocksOf, columnsOf, sectionsOf } from '../../../Support/layoutTree'
import { t, tc } from '@atrium/Support/i18n'

/*
 * A page built of blocks on a record's form (docs/site-builder.md). A saved
 * record opens the page editor, with its preview and drafts; a record not yet
 * saved, and a form without that editor, builds the tree here. Nothing is
 * emitted until something changes.
 */
const props = defineProps({
    modelValue: { type: [Object, Array, String], default: null },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: null },
    types: { type: Array, default: () => [] },
    sectionFields: { type: Array, default: () => [] },
    columnFields: { type: Array, default: () => [] },
    maxSections: { type: Number, default: 100 },
    maxColumns: { type: Number, default: 12 },
    maxBlocks: { type: Number, default: 100 },
    version: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const page = usePage()
const languages = computed(() => page.props.contentLocales || [])
const locale = inject('contentLocale', ref(null))
const language = computed(() => languages.value.find((item) => item.code === locale.value))
const multilingual = computed(() => languages.value.length > 1)

// Where the page editor of each layout field of the record is.
const builders = inject('builders', ref({}))
const editor = computed(() => builders.value?.[props.name] ?? null)

const sections = computed(() => sectionsOf(props.modelValue))
const blocks = computed(() => sections.value.flatMap(columnsOf).flatMap(blocksOf).length)

const selected = ref(null)
</script>

<template>
    <div class="layout-field" :data-layout="name" dusk="layout-editor">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
            <span v-if="label" class="block text-sm font-medium text-fg">
                {{ label }}
                <span v-if="required" class="text-danger">*</span>
            </span>
            <span v-if="!editor && multilingual && language" class="text-sm text-muted" dusk="layout-language">
                {{ t('fields.layout.language', { language: language.name }) }}
            </span>
        </div>

        <p v-if="error" class="mb-3 text-sm text-danger" role="alert" dusk="layout-error">{{ error }}</p>

        <div v-if="editor" class="flex flex-wrap items-center gap-3 rounded-md border border-field bg-surface-alt px-4 py-3">
            <p class="text-sm text-fg" dusk="layout-summary">
                {{ sections.length ? `${tc('builder.count.sections', sections.length)}, ${tc('builder.count.blocks', blocks)}` : t('builder.count.empty') }}
            </p>
            <Link :href="editor" class="inline-flex items-center rounded-md bg-accent px-4 py-2 text-sm font-medium text-on-accent hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2" dusk="layout-open-editor">
                {{ t('builder.open') }}
            </Link>
        </div>
        <Workspace
            v-else
            v-model:selected="selected"
            :model-value="modelValue"
            :types="types"
            :section-fields="sectionFields"
            :column-fields="columnFields"
            :max-sections="maxSections"
            :max-columns="maxColumns"
            :max-blocks="maxBlocks"
            :version="version"
            :disabled="disabled"
            @update:model-value="emit('update:modelValue', $event)"
        />

        <p v-if="hint" class="mt-2 text-sm text-muted">{{ hint }}</p>
    </div>
</template>

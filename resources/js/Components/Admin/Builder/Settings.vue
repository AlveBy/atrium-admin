<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, ref, watch } from 'vue'
import FormField from '../Fields/FormField.vue'
import { SMALL_BUTTON } from './classes'
import { blocksOf, columnsOf, locate, object, sectionsOf } from '../../../Support/layoutTree'
import { initialValue } from '../../../Support/contentLocale'
import { t } from '@atrium/Support/i18n'

/*
 * The settings of the selected node: the fields of a block's type, of a
 * section or of a column, and for a block the column it is in.
 *
 * Settings per screen width (step S4 of the site builder) belong in this
 * panel, beside the ones below.
 */
const props = defineProps({
    tree: { type: [Object, Array, String], default: null },
    selected: { type: String, default: null },
    types: { type: Array, required: true },
    sectionFields: { type: Array, required: true },
    columnFields: { type: Array, required: true },
    typeLabel: { type: Function, required: true },
    maxBlocks: { type: Number, required: true },
    // What the server said about the selected node.
    problem: { type: String, default: null },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['set', 'move'])

const found = computed(() => (props.selected ? locate(props.tree, props.selected) : null))
const type = computed(() => props.types.find((item) => item.type === found.value?.node.type))
const noErrors = {}

const fields = computed(() => {
    if (found.value?.kind === 'section') return props.sectionFields
    if (found.value?.kind === 'column') return props.columnFields

    return type.value?.fields ?? []
})

/** A node's fields, under names of their own: two text blocks do not share inputs. */
const entries = computed(() => fields.value.map((field) => ({
    setting: field.name,
    field: { ...field, name: `layout-${found.value.node.id}-${field.name}`, disabled: field.disabled || props.disabled },
    value: initialValue(field, object(found.value.node.settings)[field.name]),
})))

const title = computed(() => {
    const at = found.value
    if (!at) return t('builder.settings')

    const place = { section: at.s + 1, column: (at.c ?? 0) + 1, block: (at.b ?? 0) + 1 }

    if (at.kind === 'block') return t('fields.layout.places.block', { ...place, type: props.typeLabel(at.node) })

    return t(`fields.layout.places.${at.kind}`, place)
})

/** Every column a block can go to. */
const places = computed(() => sectionsOf(props.tree).flatMap((section, s) => columnsOf(section).map((column, c) => ({
    id: column.id,
    label: t('fields.layout.places.column', { section: s + 1, column: c + 1 }),
    full: blocksOf(column).length >= props.maxBlocks,
}))))

const chosen = ref(null)

watch(() => [props.selected, found.value?.column?.id], () => { chosen.value = found.value?.column?.id ?? null }, { immediate: true })
</script>

<template>
    <section :aria-label="t('builder.settings')" dusk="layout-panel">
        <h2 class="text-sm font-semibold text-fg" dusk="layout-panel-title">{{ title }}</h2>

        <p v-if="!found" class="mt-2 text-sm text-muted">{{ t('builder.nothing_selected') }}</p>

        <template v-else>
            <p v-if="problem" role="alert" class="mt-2 rounded-md border-2 border-danger bg-danger-soft px-3 py-2 text-sm font-medium text-danger-soft-fg" dusk="layout-panel-problem">⚠ {{ problem }}</p>

            <p v-if="found.kind === 'block' && !type" class="mt-2 text-sm font-medium text-danger" dusk="layout-block-unknown">
                ⚠ {{ t('fields.layout.unknown', { type: found.node.type }) }}
            </p>

            <!-- Keyed by the node: an editor of text starts anew for each. -->
            <div :key="found.node.id" class="mt-3 space-y-4">
                <div v-for="entry in entries" :key="entry.field.name" :dusk="`layout-setting-${entry.setting}`">
                    <FormField
                        :field="entry.field"
                        :model-value="entry.value"
                        :errors="noErrors"
                        @update:model-value="emit('set', { id: found.node.id, name: entry.setting, value: $event })"
                    />
                </div>
            </div>

            <div v-if="found.kind === 'block' && !disabled && places.length > 1" class="mt-5 border-t border-line pt-3">
                <label class="block text-sm font-medium text-fg" :for="`move-${found.node.id}`">{{ t('builder.move_to') }}</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    <select :id="`move-${found.node.id}`" v-model="chosen" class="min-w-0 flex-1 rounded-md border border-field bg-surface px-3 py-1.5 text-sm text-fg focus:border-focus focus:ring-focus" dusk="layout-move-to">
                        <option v-for="place in places" :key="place.id" :value="place.id" :disabled="place.full && place.id !== found.column.id">{{ place.label }}</option>
                    </select>
                    <button type="button" :class="SMALL_BUTTON" :disabled="chosen === found.column.id" dusk="layout-move" @click="emit('move', { id: found.node.id, column: chosen })">
                        {{ t('builder.move') }}
                    </button>
                </div>
            </div>
        </template>
    </section>
</template>

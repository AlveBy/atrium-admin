<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { onBeforeUnmount, onMounted, ref } from 'vue'
import { BLOCKS, sortable } from './sortable'

/*
 * The block types there are. One is dragged into a column of the structure;
 * pressed, it goes after what is selected.
 */
const props = defineProps({
    types: { type: Array, required: true },
    // No room for one more block where it would go.
    full: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'drop'])

const items = ref(null)
let sorter = null

onMounted(() => {
    sorter = sortable(items.value, {
        group: { name: BLOCKS, pull: 'clone', put: false },
        sort: false,
        draggable: '[data-palette]',
        onDrop: (event) => emit('drop', {
            type: event.item.dataset.palette,
            column: event.to.dataset.column,
            index: event.newDraggableIndex,
        }),
    })
})

onBeforeUnmount(() => sorter?.destroy())
</script>

<template>
    <section :aria-label="$t('builder.palette')" dusk="builder-palette">
        <h2 class="text-sm font-semibold text-fg">{{ $t('builder.palette') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ $t('builder.palette_hint') }}</p>

        <ul ref="items" class="mt-2 flex flex-wrap gap-2">
            <li v-for="type in props.types" :key="type.type" :data-palette="type.type" class="cursor-grab">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-field bg-surface px-2.5 py-1.5 text-sm font-medium text-fg hover:bg-hover focus:outline-none focus:ring-2 focus:ring-focus disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="full"
                    :aria-label="$t('builder.add_type', { type: type.label })"
                    :dusk="`builder-add-${type.type}`"
                    @click="emit('add', type.type)"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    {{ type.label }}
                </button>
            </li>
        </ul>
        <p v-if="full" class="mt-2 text-sm text-fg">{{ $t('builder.column_full') }}</p>
    </section>
</template>

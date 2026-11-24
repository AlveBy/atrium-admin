<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue'
import MultiSelectField from './MultiSelectField.vue'

// Roles for each site apart: a list of roles under every site's name. The
// value is an object, site id => role ids.
const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [Object, Array, String], default: () => ({}) },
    sites: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

// A record without such roles comes as [] or '': an object either way, or
// the sites' ids would be lost when the form is sent as JSON.
const bySite = computed(() => (props.modelValue && typeof props.modelValue === 'object' && ! Array.isArray(props.modelValue))
    ? props.modelValue
    : {})

const update = (site, roles) => emit('update:modelValue', { ...bySite.value, [site]: roles })
</script>

<template>
    <div :id="id" class="space-y-3" :data-site-roles="id">
        <div v-for="site in sites" :key="site.value" class="grid gap-1 sm:grid-cols-[12rem_1fr] sm:items-center">
            <label :for="`${id}-${site.value}`" class="text-sm text-fg">{{ site.label }}</label>
            <MultiSelectField
                :id="`${id}-${site.value}`"
                :model-value="bySite[site.value] || []"
                :options="roles"
                :placeholder="placeholder"
                :disabled="disabled"
                :error="error"
                @update:model-value="update(site.value, $event)"
            />
        </div>
    </div>
</template>

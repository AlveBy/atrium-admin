<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * A role: its name and what it allows, as a table of sections × actions.
 *
 * Creating, editing or deleting without viewing is useless — the records
 * cannot even be found — so ticking any of them ticks "view" too, and
 * unticking "view" clears the row.
 */
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'

const props = defineProps({
    role: { type: Object, default: null },
    sections: { type: Array, required: true },
    title: String,
})

const ACTIONS = ['view', 'create', 'update', 'delete']

const form = useForm({
    name: props.role?.name ?? '',
    permissions: [...(props.role?.permissions ?? [])],
})

const has = (section, action) => form.permissions.includes(`${section.key}.${action}`)

const isModule = (section) => ACTIONS.every((action) => section.actions.includes(action))

// Actions a module package adds to a row, such as "settings".
const extras = (section) => isModule(section) ? section.actions.filter((action) => !ACTIONS.includes(action)) : []
const hasExtras = props.sections.some((section) => extras(section).length > 0)

const toggle = (section, action) => {
    const set = new Set(form.permissions)
    const permission = `${section.key}.${action}`

    if (set.has(permission)) {
        set.delete(permission)
        if (action === 'view') ACTIONS.forEach((other) => set.delete(`${section.key}.${other}`))
    } else {
        set.add(permission)
        if (isModule(section) && ACTIONS.includes(action) && action !== 'view') set.add(`${section.key}.view`)
    }

    form.permissions = [...set]
}

const rowFull = (section) => section.actions.every((action) => has(section, action))

const toggleRow = (section) => {
    const set = new Set(form.permissions)
    const full = rowFull(section)

    section.actions.forEach((action) => full ? set.delete(`${section.key}.${action}`) : set.add(`${section.key}.${action}`))

    form.permissions = [...set]
}

const selectAll = () => {
    form.permissions = props.sections.flatMap((section) => section.actions.map((action) => `${section.key}.${action}`))
}

const submit = () => {
    if (props.role) {
        form.put(route('admin.roles.update', props.role.id))
    } else {
        form.post(route('admin.roles.store'))
    }
}
</script>

<template>
    <AdminLayout :title="title">
        <div class="mb-6">
            <Link :href="route('admin.roles.index')" class="text-muted hover:text-accent">{{ $t('menu.roles') }}</Link>
        </div>

        <div class="max-w-4xl">
            <h1 class="text-2xl font-bold text-fg mb-6">{{ title }}</h1>

            <AdminCard>
                <form class="space-y-6" data-role-form @submit.prevent="submit">
                    <div>
                        <label for="role-name" class="block text-sm font-medium text-fg mb-1">
                            {{ $t('permissions.name') }} <span class="text-danger">*</span>
                        </label>
                        <input
                            id="role-name"
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-danger">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-medium text-fg">{{ $t('permissions.permissions') }}</span>
                            <div class="flex gap-2">
                                <AdminButton type="button" size="sm" variant="outline" @click="selectAll">{{ $t('permissions.select_all') }}</AdminButton>
                                <AdminButton type="button" size="sm" variant="outline" @click="form.permissions = []">{{ $t('permissions.clear') }}</AdminButton>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $t('permissions.matrix_hint') }}</p>
                        <p v-if="form.errors.permissions" class="mt-1 text-sm text-danger">{{ form.errors.permissions }}</p>

                        <div class="mt-2 overflow-x-auto border border-line rounded-md">
                            <table class="min-w-full text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-fg">{{ $t('permissions.section') }}</th>
                                        <th v-for="action in ACTIONS" :key="action" class="px-3 py-2 w-24 text-center font-medium text-fg">
                                            {{ $t(`permissions.actions.${action}`) }}
                                        </th>
                                        <th v-if="hasExtras" class="px-3 py-2 text-left font-medium text-fg">{{ $t('permissions.other') }}</th>
                                        <th class="px-3 py-2 w-20 text-center font-medium text-fg">{{ $t('permissions.all') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="section in sections" :key="section.key" :data-section="section.key">
                                        <td class="px-3 py-1.5">
                                            {{ section.label }}
                                            <span class="ml-1 text-xs text-muted font-mono">{{ section.key }}</span>
                                        </td>
                                        <template v-if="isModule(section)">
                                            <td v-for="action in ACTIONS" :key="action" class="px-3 py-1.5 text-center">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4"
                                                    :data-permission="`${section.key}.${action}`"
                                                    :aria-label="`${section.label}: ${$t(`permissions.actions.${action}`)}`"
                                                    :checked="has(section, action)"
                                                    @change="toggle(section, action)"
                                                />
                                            </td>
                                            <td v-if="hasExtras" class="px-3 py-1.5">
                                                <label v-for="action in extras(section)" :key="action" class="inline-flex items-center gap-2 mr-3">
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4"
                                                        :data-permission="`${section.key}.${action}`"
                                                        :checked="has(section, action)"
                                                        @change="toggle(section, action)"
                                                    />
                                                    {{ $t(`permissions.actions.${action}`) }}
                                                </label>
                                            </td>
                                        </template>
                                        <td v-else :colspan="hasExtras ? 5 : 4" class="px-3 py-1.5">
                                            <label v-for="action in section.actions" :key="action" class="inline-flex items-center gap-2 mr-3">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4"
                                                    :data-permission="`${section.key}.${action}`"
                                                    :checked="has(section, action)"
                                                    @change="toggle(section, action)"
                                                />
                                                {{ $t(`permissions.actions.${action}`) }}
                                            </label>
                                        </td>
                                        <td class="px-3 py-1.5 text-center">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4"
                                                :aria-label="`${section.label}: ${$t('permissions.all')}`"
                                                :checked="rowFull(section)"
                                                @change="toggleRow(section)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <AdminButton :href="route('admin.roles.index')" variant="secondary">{{ $t('common.cancel') }}</AdminButton>
                        <AdminButton data-action="save" type="submit" :loading="form.processing">{{ $t('common.save') }}</AdminButton>
                    </div>
                </form>
            </AdminCard>
        </div>
    </AdminLayout>
</template>

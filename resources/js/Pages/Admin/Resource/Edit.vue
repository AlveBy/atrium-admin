<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { useForm, Link, Head } from '@inertiajs/vue3'
import { ref, computed, provide } from 'vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminCard from '../../../Components/Admin/UI/AdminCard.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import AdminTabs from '../../../Components/Admin/Tabs/AdminTabs.vue'
import AdminTabPanel from '../../../Components/Admin/Tabs/AdminTabPanel.vue'
import FormField from '../../../Components/Admin/Fields/FormField.vue'
import ContentLocaleSwitcher from '../../../Components/Admin/Fields/ContentLocaleSwitcher.vue'
import { useContentLocale, initialValue, fieldHasError } from '../../../Support/contentLocale'
import { recordActionHref, runRecordAction } from '../../../Support/recordActions'

const props = defineProps({
    routeName: String,
    createLabel: String,
    item: Object,
    itemTitle: String,
    fields: Array,
    fieldsByTab: Object,
    tabs: Array,
    useTabs: Boolean,
    title: String,
    adminLabel: String,
    can: { type: Object, default: () => ({}) },
    // Blocks other modules draw on the record (atrium.ui.panels): HTML from
    // the server, under the form, beside it or on a tab of its own.
    panels: { type: Array, default: () => [] },
    // Buttons on a record: a filled create form, a link, a server action.
    actions: { type: Array, default: () => [] },
    // Where the link buttons lead for this record: id => name => address.
    actionUrls: { type: [Object, Array], default: () => ({}) },
    // Where the record's history is, or null when the module keeps none.
    historyUrl: { type: String, default: null },
})

// A server action that asks first waits here for the answer.
const pendingAction = ref(null)

const runAction = (action) => {
    if (action.confirm) {
        pendingAction.value = action
    } else {
        runRecordAction(props.routeName, action, props.item)
    }
}

const confirmAction = () => {
    const action = pendingAction.value
    pendingAction.value = null
    runRecordAction(props.routeName, action, props.item)
}

// With "view" but not "update" the record opens read-only: no Save, and the
// inputs are switched off (the server refuses the save anyway).
const readOnly = computed(() => props.can.update === false)

// Active tab
const activeTab = ref(props.tabs?.[0]?.name || 'main')

// Initialize form data
const initialData = {}
if (props.useTabs && props.fieldsByTab) {
    Object.values(props.fieldsByTab).flat().forEach(field => {
        initialData[field.name] = initialValue(field, props.item[field.name])
    })
} else {
    props.fields.forEach(field => {
        initialData[field.name] = initialValue(field, props.item[field.name])
    })
}

const form = useForm(initialData)

const allFields = () => (props.useTabs && props.fieldsByTab ? Object.values(props.fieldsByTab).flat() : props.fields) || []
const { locale: contentLocale, locales: contentLocales, showSwitcher } = useContentLocale(allFields)

// Provide form data for child components (SlugField reads the name it
// generates the code from, and the section the public address hangs on)
const formData = computed(() => ({
    ...props.item,
    ...form.data()
}))
provide('formData', formData)
// The module key the AI buttons send (alveby/atrium-ai).
provide('aiModule', props.routeName?.replace(/^admin\./, '') || null)

// Use form.submit(method, url) rather than form.post()/form.put(): a field
// named e.g. "post" shadows the like-named method on the form object and the
// submit would fail with "form.post is not a function".
const submit = () => {
    form.submit('put', route(`${props.routeName}.update`, props.item.id))
}

// Check for errors in tab
const hasTabErrors = (tabName) => {
    if (!props.fieldsByTab?.[tabName]) return false
    return props.fieldsByTab[tabName].some(field => fieldHasError(form.errors, field))
}

// Get error count for tab
const getTabErrorCount = (tabName) => {
    if (!props.fieldsByTab?.[tabName]) return 0
    return props.fieldsByTab[tabName].filter(field => fieldHasError(form.errors, field)).length
}

// Tabs with error badges
const tabsWithBadges = computed(() => {
    if (!props.tabs) return []
    return props.tabs.map(tab => ({
        ...tab,
        badge: getTabErrorCount(tab.name) > 0 ? String(getTabErrorCount(tab.name)) : null
    }))
})

// A panel asking for a tab goes under the form when the form has none.
const withTabs = computed(() => Boolean(props.useTabs && props.tabs && props.fieldsByTab))
const tabPanels = computed(() => withTabs.value ? props.panels.filter(panel => panel.placement === 'tab') : [])
const sidePanels = computed(() => props.panels.filter(panel => panel.placement === 'side'))
const belowPanels = computed(() => props.panels.filter(panel => panel.placement === 'below' || (panel.placement === 'tab' && !withTabs.value)))
const panelTab = (panel) => `panel:${panel.key}`
const allTabs = computed(() => [...tabsWithBadges.value, ...tabPanels.value.map(panel => ({ name: panelTab(panel), label: panel.label }))])
</script>

<template>
    <Head :title="title" />
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <Link :href="route(`${props.routeName}.index`)" class="text-muted hover:text-accent">
                        {{ adminLabel }}
                    </Link>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-muted" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="ml-1 text-muted">{{ itemTitle || '#' + item.id }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div :class="sidePanels.length ? 'grid gap-6 xl:grid-cols-[minmax(0,56rem)_20rem] xl:items-start' : ''">
    <div class="max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-fg">{{ title }}</h1>
            <div v-if="actions.length || historyUrl" class="flex flex-wrap gap-2">
                <AdminButton v-if="historyUrl" data-action="history" variant="secondary" :href="historyUrl">
                    {{ $t('history.button') }}
                </AdminButton>
                <template v-for="action in actions" :key="action.name">
                    <AdminButton
                        v-if="action.type === 'record'"
                        :data-action="action.name"
                        variant="secondary"
                        @click="runAction(action)"
                    >
                        {{ action.label }}
                    </AdminButton>
                    <!-- A plain link: the address may be another site, which an Inertia visit cannot open. -->
                    <a
                        v-else-if="action.type === 'link' && recordActionHref(routeName, action, item, actionUrls)"
                        :href="recordActionHref(routeName, action, item, actionUrls)"
                        :target="action.new_tab ? '_blank' : null"
                        :rel="action.new_tab ? 'noopener' : null"
                        :data-action="action.name"
                        class="inline-flex items-center justify-center rounded-md border border-line bg-off px-4 py-2 text-sm font-medium text-off-fg transition-colors hover:border-field focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                    >
                        {{ action.label }}
                        <svg class="ml-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <span v-if="action.new_tab" class="sr-only">{{ $t('actions.new_tab') }}</span>
                    </a>
                    <AdminButton
                        v-else-if="action.type === 'create'"
                        :data-action="action.name"
                        variant="secondary"
                        :href="recordActionHref(routeName, action, item)"
                    >
                        {{ action.label }}
                    </AdminButton>
                </template>
            </div>
        </div>

        <AdminCard>
            <p v-if="readOnly" data-read-only class="mb-6 rounded-md border border-field bg-surface-alt px-4 py-3 text-sm text-fg">
                {{ $t('resource.form.read_only') }}
            </p>

            <form @submit.prevent="submit" data-admin-form="true" :data-resource="routeName" data-context="edit" :data-item-id="item.id">
                <fieldset :disabled="readOnly" class="contents">
                <ContentLocaleSwitcher
                    v-if="showSwitcher"
                    v-model="contentLocale"
                    :locales="contentLocales"
                    :fields="allFields()"
                    :errors="form.errors"
                />

                <!-- With Tabs -->
                <template v-if="useTabs && tabs && fieldsByTab">
                    <AdminTabs :tabs="allTabs" v-model="activeTab">
                        <template #default="{ activeTab: currentTab }">
                            <AdminTabPanel
                                v-for="tab in tabs"
                                :key="tab.name"
                                :name="tab.name"
                                :activeTab="currentTab"
                            >
                                <div class="space-y-6">
                                    <FormField
                                        v-for="field in fieldsByTab[tab.name]"
                                        :key="field.name"
                                        :field="field"
                                        v-model="form[field.name]"
                                        :errors="form.errors"
                                    />

                                    <!-- Empty tab message -->
                                    <div
                                        v-if="!fieldsByTab[tab.name]?.length"
                                        class="text-center py-12 text-muted"
                                    >
                                        <p>{{ $t('resource.form.empty_tab') }}</p>
                                    </div>
                                </div>
                            </AdminTabPanel>
                            <AdminTabPanel
                                v-for="panel in tabPanels"
                                :key="panelTab(panel)"
                                :name="panelTab(panel)"
                                :activeTab="currentTab"
                            >
                                <div class="text-sm text-fg" :data-panel="panel.key" data-panel-html v-html="panel.html" />
                            </AdminTabPanel>
                        </template>
                    </AdminTabs>
                </template>

                <!-- Without Tabs (backward compatibility) -->
                <template v-else>
                    <div class="space-y-6">
                        <FormField
                            v-for="field in fields"
                            :key="field.name"
                            :field="field"
                            v-model="form[field.name]"
                            :errors="form.errors"
                        />
                    </div>
                </template>

                </fieldset>

                <!-- Form buttons -->
                <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t">
                    <AdminButton data-action="cancel" :href="route(`${props.routeName}.index`)" variant="secondary">
                        {{ readOnly ? $t('common.back') : $t('common.cancel') }}
                    </AdminButton>
                    <AdminButton v-if="!readOnly" data-action="save" type="submit" :loading="form.processing">
                        {{ $t('common.save') }}
                    </AdminButton>
                </div>
            </form>
        </AdminCard>

        <AdminCard v-for="panel in belowPanels" :key="panel.key" :title="panel.label" :data-panel="panel.key" class="mt-6">
            <div class="text-sm text-fg" data-panel-html v-html="panel.html" />
        </AdminCard>
    </div>

    <aside v-if="sidePanels.length" class="space-y-6" data-panels="side">
        <AdminCard v-for="panel in sidePanels" :key="panel.key" :title="panel.label" :data-panel="panel.key">
            <div class="text-sm text-fg" data-panel-html v-html="panel.html" />
        </AdminCard>
    </aside>
    </div>
    <AdminModal :show="pendingAction !== null" :title="$t('actions.confirm_title')" @close="pendingAction = null">
        <p data-action-question class="text-fg">{{ pendingAction?.confirm }}</p>

        <template #footer>
            <AdminButton data-action="action-cancel" variant="secondary" @click="pendingAction = null">
                {{ $t('common.cancel') }}
            </AdminButton>
            <AdminButton data-action="action-confirm" @click="confirmAction">
                {{ pendingAction?.label }}
            </AdminButton>
        </template>
    </AdminModal>
</template>

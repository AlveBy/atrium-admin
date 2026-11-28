<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'
import ContentLocaleSwitcher from '../../../Components/Admin/Fields/ContentLocaleSwitcher.vue'
import Workspace from '../../../Components/Admin/Builder/Workspace.vue'
import Preview from '../../../Components/Admin/Builder/Preview.vue'
import { useContentLocale } from '../../../Support/contentLocale'
import { ajaxHeaders, responseErrorMessage } from '../../../Support/csrf'
import { locate, same } from '../../../Support/layoutTree'
import { t } from '../../../Support/i18n'

/*
 * The page editor of a record's layout field. What is edited here stays here
 * until it is saved as a draft (kept, not on the site) or published (saved to
 * the record). An earlier version opens into the editor, to be published like
 * any other change.
 */
const props = defineProps({
    title: { type: String, required: true },
    adminLabel: { type: String, required: true },
    routeName: { type: String, required: true },
    // The layout field as the form describes it: the block types and their settings.
    field: { type: Object, required: true },
    tree: { type: [Object, Array], default: null },
    published: { type: [Object, Array], default: null },
    // The fingerprint of the published tree this editing started from.
    base: { type: String, required: true },
    // { id, at, author, stale } or null.
    draft: { type: Object, default: null },
    versions: { type: Array, default: () => [] },
    // The record is switched off: publishing does not put it on the site.
    hidden: { type: Boolean, default: false },
    // edit, preview (null without one), draft, discard, publish.
    urls: { type: Object, required: true },
})

const METHODS = { draft: 'PUT', discard: 'DELETE', publish: 'POST' }

const tree = ref(props.tree)
// The tree as it is kept: the draft, or what is published.
const saved = ref(props.tree)
const published = ref(props.published)
const base = ref(props.base)
const draft = ref(props.draft)
const versions = ref(props.versions)

const selected = ref(null)
const drawn = ref(null)
const failed = ref(null)
const problem = ref('')
const notice = ref('')
const busy = ref(null)
// A step the server refused because someone else was faster: { step, message }.
const conflict = ref(null)
const asking = ref(false)
const history = ref(false)

const dirty = computed(() => !same(tree.value, saved.value))
const publishable = computed(() => dirty.value || draft.value !== null || !same(tree.value, published.value))

const { locale, locales, showSwitcher } = useContentLocale(() => [props.field])

provide('formData', computed(() => ({ name: props.title })))
provide('aiModule', props.routeName.replace(/^admin\./, ''))

const date = (at) => new Date(at).toLocaleString((usePage().props.locale || 'en').replace('_', '-'), { dateStyle: 'medium', timeStyle: 'short' })

// A sign and words for each state: no state is told by colour.
const status = computed(() => {
    if (dirty.value) return { key: 'unsaved', sign: '●', text: t('builder.status.unsaved') }

    if (draft.value) {
        const { at, author } = draft.value

        return { key: 'draft', sign: '◐', text: t(author ? 'builder.status.draft_by' : 'builder.status.draft', { date: date(at), author }) }
    }

    return { key: 'published', sign: '✓', text: t('builder.status.published') }
})

watch(tree, () => {
    failed.value = null
    problem.value = ''
    notice.value = ''
})

function refuse(answer) {
    const errors = Object.values(answer.errors ?? {}).flat()

    problem.value = errors.length ? errors.join(' ') : (answer.message || t('builder.failed'))
    failed.value = answer.node ?? null

    if (failed.value) {
        selected.value = failed.value
    }
}

/** Opens a tree in the editor; what was selected stays so if it is there. */
function open(next) {
    tree.value = next

    if (selected.value && !locate(next, selected.value)) {
        selected.value = null
    }
}

function finished(step, sent, answer) {
    if (step === 'draft') {
        draft.value = answer.draft
        saved.value = sent
    } else {
        open(answer.tree)
        saved.value = answer.tree
        published.value = answer.published
        base.value = answer.base
        draft.value = null
        versions.value = answer.versions
    }
}

async function send(step, force = false) {
    const sent = tree.value

    busy.value = step
    conflict.value = null
    failed.value = null
    problem.value = ''
    notice.value = ''

    try {
        const response = await fetch(props.urls[step], {
            method: METHODS[step],
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                draft: draft.value?.id ?? null,
                force,
                ...(step === 'discard' ? {} : { tree: sent ?? null, base: base.value }),
            }),
        })

        if (response.status === 409) {
            conflict.value = { step, message: (await response.json()).message }
        } else if (response.status === 422) {
            refuse(await response.json())
        } else if (!response.ok) {
            problem.value = await responseErrorMessage(response, t('builder.failed'))
        } else {
            finished(step, sent, await response.json())
            // After the tree has settled: a change to it clears the notice.
            await Promise.resolve()
            notice.value = t(`builder.notices.${step}`)
        }
    } catch {
        problem.value = t('builder.failed')
    } finally {
        busy.value = null
    }
}

function discard() {
    asking.value = false
    send('discard')
}

async function openVersion(version) {
    busy.value = 'version'
    history.value = false

    try {
        const response = await fetch(version.url, { headers: ajaxHeaders() })

        if (!response.ok) {
            problem.value = await responseErrorMessage(response, t('builder.failed'))
            return
        }

        open((await response.json()).tree)
        await Promise.resolve()
        notice.value = t('builder.notices.version')
    } catch {
        problem.value = t('builder.failed')
    } finally {
        busy.value = null
    }
}

// Leaving with changes not kept asks first.
let leaving = false

function warn(event) {
    if (dirty.value && !leaving) {
        event.preventDefault()
        event.returnValue = ''
    }
}

/** The editor again, with what the server holds now. */
function reload() {
    leaving = true
    router.visit(window.location.href, { preserveState: false, preserveScroll: true, replace: true })
}

let stopAsking = null

onMounted(() => {
    window.addEventListener('beforeunload', warn)
    stopAsking = router.on('before', (event) => {
        if (dirty.value && !leaving && !window.confirm(t('builder.leave'))) {
            event.preventDefault()
        }
    })
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warn)
    stopAsking?.()
})

const banner = 'mb-3 rounded-md border px-4 py-2 text-sm font-medium'
</script>

<template>
    <Head :title="t('builder.title', { title })" />

    <nav class="mb-3 text-sm" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><Link :href="route(`${routeName}.index`)" class="text-muted hover:text-accent">{{ adminLabel }}</Link></li>
            <li class="text-muted" aria-hidden="true">›</li>
            <li><Link :href="urls.edit" class="text-muted hover:text-accent" dusk="builder-back">{{ title }}</Link></li>
        </ol>
    </nav>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-fg">{{ t('builder.title', { title }) }}</h1>
        <p class="rounded-md border border-field bg-surface px-3 py-1 text-sm text-fg" role="status" dusk="builder-status" :data-status="status.key">
            <span aria-hidden="true">{{ status.sign }}</span> {{ status.text }}
        </p>
        <div class="ml-auto flex flex-wrap gap-2">
            <AdminButton variant="secondary" :href="urls.edit">{{ t('builder.back') }}</AdminButton>
            <AdminButton v-if="versions.length" variant="secondary" :disabled="busy !== null" dusk="builder-versions" @click="history = true">{{ t('builder.versions.button') }}</AdminButton>
            <AdminButton v-if="dirty || draft" variant="secondary" :disabled="busy !== null" dusk="builder-discard" @click="asking = true">{{ t('builder.discard.button') }}</AdminButton>
            <AdminButton variant="outline" :disabled="busy !== null || !dirty" :loading="busy === 'draft'" dusk="builder-save-draft" @click="send('draft')">{{ t('builder.save_draft') }}</AdminButton>
            <AdminButton :disabled="busy !== null || !publishable" :loading="busy === 'publish'" dusk="builder-publish" @click="send('publish')">{{ t('builder.publish') }}</AdminButton>
        </div>
    </div>

    <p v-if="draft?.stale" :class="[banner, 'border-warning-soft-fg bg-warning-soft text-warning-soft-fg']" dusk="builder-stale">⚠ {{ t('builder.stale') }}</p>
    <p v-if="hidden" :class="[banner, 'border-field bg-surface-alt text-fg']" dusk="builder-hidden">ⓘ {{ t('builder.hidden') }}</p>
    <p v-if="notice" :class="[banner, 'border-success bg-success-soft text-success-soft-fg']" role="status" dusk="builder-notice">✓ {{ notice }}</p>
    <p v-if="problem" :class="[banner, 'border-2 border-danger bg-danger-soft text-danger-soft-fg']" role="alert" dusk="builder-problem">⚠ {{ problem }}</p>

    <ContentLocaleSwitcher v-if="showSwitcher" v-model="locale" :locales="locales" :fields="[field]" />

    <!-- Nothing is changed while the server is putting another tree here. -->
    <div :inert="busy === 'publish' || busy === 'discard' || busy === 'version'">
        <Workspace
            v-model="tree"
            v-model:selected="selected"
            :types="field.types"
            :section-fields="field.sectionFields"
            :column-fields="field.columnFields"
            :max-sections="field.maxSections"
            :max-columns="field.maxColumns"
            :max-blocks="field.maxBlocks"
            :version="field.version"
            :failed="failed"
            :problem="problem"
            :drawn="drawn"
        >
            <template v-if="urls.preview" #preview="{ placeOf }">
                <Preview
                    :url="urls.preview"
                    :tree="tree"
                    :language="locale"
                    :selected="selected"
                    :label-of="(id) => t('builder.preview.selected', { place: placeOf(tree, id) })"
                    @select="selected = $event"
                    @drawn="drawn = $event"
                />
            </template>
        </Workspace>
    </div>

    <AdminModal :show="asking" :title="t('builder.discard.title')" @close="asking = false">
        <p class="text-fg">{{ t('builder.discard.question') }}</p>
        <template #footer>
            <AdminButton variant="secondary" @click="asking = false">{{ t('common.cancel') }}</AdminButton>
            <AdminButton variant="danger" dusk="builder-discard-confirm" @click="discard">{{ t('builder.discard.button') }}</AdminButton>
        </template>
    </AdminModal>

    <AdminModal :show="conflict !== null" :title="t('builder.conflict.title')" @close="conflict = null">
        <p class="text-fg" dusk="builder-conflict">{{ conflict?.message }}</p>
        <p class="mt-2 text-sm text-muted">{{ t('builder.conflict.hint') }}</p>
        <template #footer>
            <AdminButton variant="secondary" dusk="builder-conflict-reload" @click="reload">{{ t('builder.conflict.reload') }}</AdminButton>
            <AdminButton variant="danger" dusk="builder-conflict-force" @click="send(conflict.step, true)">{{ t('builder.conflict.force') }}</AdminButton>
        </template>
    </AdminModal>

    <AdminModal :show="history" :title="t('builder.versions.title')" max-width="lg" @close="history = false">
        <p class="text-sm text-muted">{{ t('builder.versions.hint') }}</p>
        <p v-if="dirty" class="mt-2 text-sm font-medium text-fg">⚠ {{ t('builder.versions.replaces') }}</p>
        <ul class="mt-3 max-h-96 divide-y divide-line overflow-y-auto">
            <li v-for="(version, index) in versions" :key="version.id" class="flex items-center justify-between gap-3 py-2" :dusk="`builder-version-${index}`">
                <div class="min-w-0 text-sm text-fg">
                    <p class="font-medium">
                        {{ version.at ? date(version.at) : t('history.versions.baseline') }}
                        <span v-if="version.current" class="ml-1 rounded border border-field px-1.5 text-xs">{{ t('builder.versions.current') }}</span>
                    </p>
                    <p v-if="version.at" class="text-muted">
                        {{ version.admin || t('history.system') }}<template v-if="version.source"> · {{ t(`history.sources.${version.source}`) }}</template>
                    </p>
                </div>
                <AdminButton variant="secondary" size="sm" :dusk="`builder-open-version-${index}`" @click="openVersion(version)">{{ t('builder.versions.open') }}</AdminButton>
            </li>
        </ul>
    </AdminModal>
</template>

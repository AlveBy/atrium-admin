<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "Backups" screen of alveby/atrium-backup: archives on every
 * destination, "back up now", download, restore with a dry run, and the log.
 *
 * The module's strings come in the `labels` prop (they are not in the panel's
 * own dictionary). While a backup or restore runs, the page polls the status.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, usePage, Head } from '@inertiajs/vue3'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import AdminModal from '@atrium/Components/Admin/UI/AdminModal.vue'
import { ajaxHeaders, responseErrorMessage } from '@atrium/Support/csrf'

const props = defineProps({
    backups: Array,
    running: Object,
    log: Array,
    health: Array,
    settings: Object,
    labels: Object,
})

const page = usePage()
const locale = computed(() => String(page.props.locale || 'en').replace('_', '-'))

function l(key, params = {}) {
    let line = props.labels?.[key] ?? key

    for (const [name, value] of Object.entries(params)) {
        line = line.replaceAll(`:${name}`, String(value ?? ''))
    }

    return line
}

function size(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let value = Number(bytes) || 0
    let unit = 0

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024
        unit++
    }

    return `${value.toLocaleString(locale.value, { maximumFractionDigits: unit > 1 ? 1 : 0 })} ${units[unit]}`
}

function date(iso) {
    return iso ? new Date(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' }) : ''
}

// --- status, polled while something runs ------------------------------------

const state = ref({ backups: props.backups, running: props.running, log: props.log })
let timer = null

watch(() => [props.backups, props.running, props.log], () => {
    state.value = { backups: props.backups, running: props.running, log: props.log }
})

async function poll() {
    const response = await fetch(route('admin.backups.status'), { headers: ajaxHeaders() }).catch(() => null)

    if (response?.ok) {
        const next = await response.json()
        const finished = state.value.running && !next.running
        state.value = next

        if (finished) {
            router.reload({ only: ['health', 'backups', 'running', 'log'] })
        }
    }
}

watch(() => state.value.running, (running) => {
    clearInterval(timer)
    timer = running ? setInterval(poll, 3000) : null
}, { immediate: true })

onBeforeUnmount(() => clearInterval(timer))

// --- back up now --------------------------------------------------------------

const starting = ref(false)

function backUp(only = null) {
    starting.value = true
    router.post(route('admin.backups.store'), { only }, {
        preserveScroll: true,
        onSuccess: () => poll(),
        onFinish: () => { starting.value = false },
    })
}

// --- delete -------------------------------------------------------------------

const toDelete = ref(null)

function destroy() {
    router.delete(route('admin.backups.destroy', toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => { toDelete.value = null },
    })
}

// --- restore ------------------------------------------------------------------

const restoring = ref(null)
const plan = ref(null)
const planError = ref('')
const planLoading = ref(false)
const restoreForm = ref({})
const restoreErrors = ref({})
const submitting = ref(false)

function openRestore(backup) {
    restoring.value = backup
    plan.value = null
    planError.value = ''
    restoreErrors.value = {}
    restoreForm.value = { password: '', database: true, files: true, delete_extra: false, safety_backup: true, confirm: '' }
}

async function preview() {
    planLoading.value = true
    planError.value = ''

    try {
        const response = await fetch(route('admin.backups.plan', restoring.value.id), {
            method: 'POST',
            headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ password: restoreForm.value.password || null }),
        })

        if (!response.ok) {
            planError.value = await responseErrorMessage(response, l('preview_failed'))
            plan.value = null

            return
        }

        plan.value = (await response.json()).plan
    } catch {
        planError.value = l('preview_failed')
    } finally {
        planLoading.value = false
    }
}

const canRestore = computed(() => plan.value
    && (restoreForm.value.database || restoreForm.value.files)
    && restoreForm.value.confirm.trim() === restoring.value?.file)

function restore() {
    submitting.value = true
    router.post(route('admin.backups.restore', restoring.value.id), {
        ...restoreForm.value,
        confirm: restoreForm.value.confirm.trim(),
        password: restoreForm.value.password || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { restoring.value = null; poll() },
        onError: (errors) => { restoreErrors.value = errors },
        onFinish: () => { submitting.value = false },
    })
}

// --- settings and log ---------------------------------------------------------

const schedule = computed(() => {
    const s = props.settings?.schedule || {}

    if (s.frequency === 'daily') return l('schedule_daily', { time: s.time })
    if (s.frequency === 'weekly') return l('schedule_weekly', { weekday: props.settings.weekday, time: s.time })

    return l('schedule_off')
})

const retention = computed(() => {
    const r = props.settings?.retention || {}

    return l('retention_text', { all: r.keep_all_days, daily: r.daily, weekly: r.weekly, monthly: r.monthly, yearly: r.yearly })
})

const statusClass = {
    ok: 'bg-success-soft text-success-soft-fg',
    partial: 'bg-warning-soft text-warning-soft-fg',
    failed: 'bg-danger-soft text-danger-soft-fg',
}

function details(entry) {
    const parts = []

    if (entry.trigger) parts.push(l(`trigger_${entry.trigger}`))
    if (entry.by) parts.push(entry.by)
    if (entry.file) parts.push(entry.disk ? `${entry.file} (${entry.disk})` : entry.file)
    if (entry.disks?.length) parts.push(l('copied_to', { disks: entry.disks.join(', ') }))
    if (entry.failed?.length) parts.push(l('not_copied_to', { disks: entry.failed.map((f) => f.disk ?? f).join(', ') }))
    if (entry.action === 'restore' && entry.status === 'ok') {
        parts.push(l('restored', { databases: (entry.databases || []).join(', ') || '—', files: entry.files ?? 0, deleted: entry.deleted ?? 0 }))
    }
    if (entry.seconds !== undefined) parts.push(l('seconds', { seconds: entry.seconds }))
    if (entry.message) parts.push(entry.message)

    return parts.join(' · ')
}
</script>

<template>
    <Head :title="l('menu')" />
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-fg">{{ labels.menu }}</h1>
            <p class="mt-1 text-sm text-muted">{{ l('subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <AdminButton dusk="backup-now" :loading="starting" :disabled="!!state.running" @click="backUp()">{{ l('back_up_now') }}</AdminButton>
            <AdminButton variant="secondary" :disabled="starting || !!state.running" @click="backUp('database')">{{ l('only_database') }}</AdminButton>
            <AdminButton variant="secondary" :disabled="starting || !!state.running" @click="backUp('files')">{{ l('only_files') }}</AdminButton>
        </div>
    </div>

    <div v-if="state.running" dusk="backup-running" role="status" class="mb-6 flex items-center gap-3 p-4 bg-accent-soft text-accent-soft-fg border border-line rounded-lg text-sm">
        <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        {{ l(state.running.action === 'restore' ? 'running_restore' : 'running_backup', { time: date(state.running.started_at) }) }}
    </div>

    <div class="space-y-6">
        <AdminCard :title="l('archives')" no-padding>
            <p v-if="!state.backups.length" class="p-6 text-sm text-muted">{{ l('empty') }}</p>
            <div v-else class="overflow-x-auto">
                <table dusk="backups" class="min-w-full text-sm">
                    <thead class="bg-surface-alt">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('file') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('destination') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('date') }}</th>
                            <th class="px-4 py-2 text-right font-medium text-fg">{{ l('size') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="backup in state.backups" :key="backup.id" :data-backup="backup.file" dusk="backup">
                            <td class="px-4 py-2 font-mono text-fg">{{ backup.file }}</td>
                            <td class="px-4 py-2 text-fg">{{ backup.disk }}</td>
                            <td class="px-4 py-2 text-fg">{{ date(backup.date) }}</td>
                            <td class="px-4 py-2 text-right text-fg whitespace-nowrap">{{ size(backup.size) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                <a :href="backup.download_url" class="text-accent underline">{{ l('download') }}</a>
                                <button type="button" dusk="restore" class="text-accent underline" :disabled="!!state.running" @click="openRestore(backup)">{{ l('restore') }}</button>
                                <button type="button" dusk="delete" class="text-danger underline" @click="toDelete = backup">{{ l('delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminCard>

        <div class="grid gap-6 lg:grid-cols-2">
            <AdminCard :title="l('destinations')">
                <ul class="space-y-4">
                    <li v-for="destination in health" :key="destination.disk" :data-destination="destination.disk" :dusk="`destination-${destination.disk}`">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-fg">{{ destination.disk }}</span>
                            <span
                                class="px-2 py-0.5 rounded text-xs font-medium"
                                :class="!destination.reachable || !destination.healthy ? statusClass.failed : statusClass.ok"
                            >{{ !destination.reachable ? l('unreachable') : (destination.healthy ? l('healthy') : l('unhealthy')) }}</span>
                        </div>
                        <p v-if="destination.reachable" class="mt-1 text-sm text-fg">
                            {{ l('stored', { count: destination.count, size: size(destination.used) }) }} ·
                            {{ destination.newest ? l('newest', { date: date(destination.newest) }) : l('no_archive') }}
                        </p>
                        <ul v-if="destination.problems.length" class="mt-1 list-disc pl-5 text-sm text-danger">
                            <li v-for="problem in destination.problems" :key="problem">{{ problem }}</li>
                        </ul>
                    </li>
                </ul>
            </AdminCard>

            <AdminCard :title="l('settings')" :subtitle="l('settings_note')">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    <dt class="font-medium text-fg">{{ l('databases') }}</dt>
                    <dd class="sm:col-span-2 text-fg">{{ settings.databases.map((d) => `${d.connection} (${d.driver})`).join(', ') || l('nothing') }}</dd>
                    <dt class="font-medium text-fg">{{ l('folders') }}</dt>
                    <dd class="sm:col-span-2 text-fg break-all">{{ settings.folders.join(', ') || l('nothing') }}</dd>
                    <dt class="font-medium text-fg">{{ l('destinations') }}</dt>
                    <dd class="sm:col-span-2 text-fg">{{ settings.destinations.join(', ') }}</dd>
                    <dt class="font-medium text-fg">{{ l('schedule') }}</dt>
                    <dd class="sm:col-span-2 text-fg">{{ schedule }}<span v-if="settings.queue">. {{ l('queue', { queue: settings.queue }) }}</span></dd>
                    <dt class="font-medium text-fg">{{ l('retention') }}</dt>
                    <dd class="sm:col-span-2 text-fg">{{ retention }}</dd>
                    <dt class="font-medium text-fg">{{ l('encryption') }}</dt>
                    <dd class="sm:col-span-2 text-fg">{{ settings.encrypted ? l('encrypted') : l('not_encrypted') }}</dd>
                </dl>
            </AdminCard>
        </div>

        <AdminCard :title="l('log')" no-padding>
            <p v-if="!state.log.length" class="p-6 text-sm text-muted">{{ l('log_empty') }}</p>
            <div v-else class="overflow-x-auto">
                <table dusk="backup-log" class="min-w-full text-sm">
                    <thead class="bg-surface-alt">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('when') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('what') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('result') }}</th>
                            <th class="px-4 py-2 text-left font-medium text-fg">{{ l('details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="(entry, index) in state.log" :key="index">
                            <td class="px-4 py-2 text-fg whitespace-nowrap">{{ date(entry.at) }}</td>
                            <td class="px-4 py-2 text-fg">{{ l(`action_${entry.action}`) }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-medium" :class="statusClass[entry.status] || statusClass.failed">{{ l(`status_${entry.status}`) }}</span>
                            </td>
                            <td class="px-4 py-2 text-fg break-words">{{ details(entry) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminCard>
    </div>

    <AdminModal :show="!!toDelete" :title="l('delete')" @close="toDelete = null">
        <div class="p-6">
            <p class="text-fg">{{ toDelete ? l('delete_confirm', { file: toDelete.file, disk: toDelete.disk }) : '' }}</p>
            <div class="mt-6 flex justify-end gap-2">
                <AdminButton variant="secondary" @click="toDelete = null">{{ l('cancel') }}</AdminButton>
                <AdminButton dusk="confirm-delete" variant="danger" @click="destroy">{{ l('delete') }}</AdminButton>
            </div>
        </div>
    </AdminModal>

    <AdminModal :show="!!restoring" max-width="2xl" :title="restoring ? l('restore_title', { file: restoring.file }) : ''" @close="restoring = null">
        <div v-if="restoring" dusk="restore-dialog" class="p-6 space-y-5 text-sm">
            <p class="text-fg">{{ l('restore_intro') }}</p>

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-48">
                    <label for="backup-password" class="block font-medium text-fg">{{ l('password') }}</label>
                    <input id="backup-password" v-model="restoreForm.password" type="password" autocomplete="off" class="mt-1 block w-full px-3 py-2 border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus" />
                    <p class="mt-1 text-xs text-muted">{{ l('password_hint') }}</p>
                </div>
                <AdminButton dusk="preview" variant="secondary" :loading="planLoading" @click="preview">{{ l('preview') }}</AdminButton>
            </div>
            <p v-if="planError" data-error class="text-danger" role="alert">{{ planError }}</p>

            <template v-if="plan">
                <p v-if="plan.created_at" class="text-fg">{{ l('made', { date: date(plan.created_at) }) }}</p>

                <div v-if="plan.databases.length" class="overflow-x-auto border border-line rounded-md">
                    <table dusk="plan-databases" class="min-w-full">
                        <thead class="bg-surface-alt">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-fg">{{ l('connection') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-fg">{{ l('driver') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('dump') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-fg">{{ l('replaces') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="database in plan.databases" :key="database.connection">
                                <td class="px-3 py-1.5 text-fg">{{ database.connection }}</td>
                                <td class="px-3 py-1.5 text-fg">{{ database.driver }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ size(database.size) }}</td>
                                <td class="px-3 py-1.5 text-fg break-all">{{ database.restorable ? database.target : l('skipped') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="plan.folders.length" class="overflow-x-auto border border-line rounded-md">
                    <table dusk="plan-folders" class="min-w-full">
                        <thead class="bg-surface-alt">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-fg">{{ l('into') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('files') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('new') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('changed') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('unchanged') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-fg">{{ l('extra') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="folder in plan.folders" :key="folder.prefix">
                                <td class="px-3 py-1.5 text-fg break-all">{{ folder.target }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ folder.files }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ folder.new }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ folder.changed }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ folder.unchanged }}</td>
                                <td class="px-3 py-1.5 text-right text-fg">{{ folder.extra }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-if="plan.warnings.length" class="list-disc pl-5 bg-warning-soft text-warning-soft-fg border border-line rounded-md p-3 space-y-1">
                    <li v-for="warning in plan.warnings" :key="warning" class="ml-3">{{ warning }}</li>
                </ul>

                <fieldset class="space-y-2">
                    <label class="flex items-center gap-2 text-fg"><input v-model="restoreForm.database" type="checkbox" class="h-4 w-4 text-accent border-field rounded focus:ring-focus" /> {{ l('restore_database') }}</label>
                    <label class="flex items-center gap-2 text-fg"><input v-model="restoreForm.files" type="checkbox" class="h-4 w-4 text-accent border-field rounded focus:ring-focus" /> {{ l('restore_files') }}</label>
                    <label class="flex items-center gap-2 text-fg ml-6"><input v-model="restoreForm.delete_extra" type="checkbox" class="h-4 w-4 text-accent border-field rounded focus:ring-focus" :disabled="!restoreForm.files" /> {{ l('delete_extra') }}</label>
                    <label class="flex items-center gap-2 text-fg"><input v-model="restoreForm.safety_backup" type="checkbox" class="h-4 w-4 text-accent border-field rounded focus:ring-focus" /> {{ l('safety_backup') }}</label>
                </fieldset>

                <div class="p-4 bg-surface text-fg border-2 border-danger rounded-lg">
                    <p class="font-medium">{{ l('risks') }}</p>
                    <ul class="mt-2 list-disc pl-5 space-y-1">
                        <li v-if="restoreForm.database">{{ l('risk_database', { date: date(plan.created_at || restoring.date) }) }}</li>
                        <li v-if="restoreForm.database">{{ l('risk_sessions') }}</li>
                        <li v-if="restoreForm.files">{{ l('risk_files') }}</li>
                        <li>{{ l('risk_site') }}</li>
                    </ul>
                </div>

                <div>
                    <label for="backup-confirm" class="block font-medium text-fg">{{ l('confirm', { file: restoring.file }) }}</label>
                    <input id="backup-confirm" dusk="restore-confirm" v-model="restoreForm.confirm" type="text" autocomplete="off" spellcheck="false" class="mt-1 block w-full px-3 py-2 font-mono border border-field rounded-md shadow-sm focus:outline-none focus:ring-focus focus:border-focus" />
                    <p v-if="restoreErrors.confirm" data-error class="mt-1 text-danger">{{ restoreErrors.confirm }}</p>
                </div>
            </template>

            <div class="flex justify-end gap-2">
                <AdminButton dusk="cancel-restore" variant="secondary" @click="restoring = null">{{ l('cancel') }}</AdminButton>
                <AdminButton dusk="start-restore" variant="danger" :loading="submitting" :disabled="!canRestore" @click="restore">{{ l('start_restore') }}</AdminButton>
            </div>
        </div>
    </AdminModal>
</template>

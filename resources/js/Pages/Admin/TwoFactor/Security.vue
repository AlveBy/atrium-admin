<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * The "My sign-in security" screen of alveby/atrium-2fa: the administrator's
 * own authenticator apps, passkeys, phone, e-mailed code, recovery codes,
 * trusted devices and recent sign-ins. The module's strings come in the `t`
 * prop; the server words dates and counts, so they match the panel's
 * language.
 */
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import AdminButton from '@atrium/Components/Admin/UI/AdminButton.vue'
import AdminCard from '@atrium/Components/Admin/UI/AdminCard.vue'
import ModuleNotice from '@atrium/Components/Admin/UI/ModuleNotice.vue'
import EventsTable from '@atrium/Components/Admin/TwoFactor/EventsTable.vue'
import { label } from '@atrium/Support/labels'
import { registerPasskey } from '@atrium/Support/passkey'

const props = defineProps({
    t: { type: Object, required: true },
    status: { type: String, default: null },
    error: { type: String, default: null },
    mustEnrol: Boolean,
    required: Boolean,
    passwordless: Boolean,
    allowed: { type: Array, required: true },
    recoveryCodes: { type: Array, default: null },
    factors: { type: Array, required: true },
    totp: { type: Object, default: null },
    sms: { type: Object, default: null },
    email: { type: String, required: true },
    recoveryLeft: { type: String, required: true },
    trustedDays: { type: Number, required: true },
    devices: { type: Array, required: true },
    events: { type: Array, required: true },
    urls: { type: Object, required: true },
})

const l = (key, params = {}) => label(props.t, key, params)
const page = usePage()
const errors = computed(() => page.props.errors || {})
const has = (type) => props.factors.some((factor) => factor.type === type)
const allows = (type) => props.allowed.includes(type)

const busy = ref(false)
const totpName = ref('')
const totpCode = ref('')
const passkeyName = ref('')
const passkeyMessage = ref('')
const phone = ref('')
const smsCode = ref('')

function send(method, url, data = {}) {
    busy.value = true
    router[method](url, data, { preserveScroll: true, onFinish: () => { busy.value = false } })
}

const supportsPasskeys = typeof window !== 'undefined' && Boolean(window.PublicKeyCredential)

async function addPasskey() {
    passkeyMessage.value = ''
    busy.value = true

    try {
        const answer = await registerPasskey(props.urls['passkeys.options'], props.urls['passkeys.store'], { name: passkeyName.value })

        // The address carries an #anchor for a page of its own; a visit wants it without.
        router.visit((answer.redirect || window.location.href).split('#')[0], {
            preserveScroll: true,
            onFinish: () => { busy.value = false },
        })
    } catch (error) {
        busy.value = false
        passkeyMessage.value = error && error.name === 'NotAllowedError' ? l('passkey.cancelled') : (error && error.message) || l('passkey.cancelled')
    }
}

const field = 'block w-full rounded-md border border-field bg-surface text-fg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-focus'
const code = `${field} text-center text-xl tracking-widest tabular-nums`
</script>

<template>
    <AdminLayout :title="l('security.title')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-fg">{{ l('security.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ required ? l('security.required') : l('security.optional') }}</p>
        </div>

        <div dusk="a2f-security" class="max-w-3xl space-y-6">
            <div v-if="mustEnrol" role="alert" dusk="a2f-must-enrol" class="flex items-start gap-3 p-4 bg-danger-soft border border-danger text-danger-soft-fg rounded">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <span><span class="sr-only">{{ $t('actions.error') }}: </span>{{ l('security.must_enrol') }}</span>
            </div>

            <div>
                <ModuleNotice prefix="a2f" :status="status" :errors="[error, errors.factors].filter(Boolean)" />
            </div>

            <AdminCard v-if="recoveryCodes" :title="l('security.recovery_new')">
                <section id="recovery-codes" role="status" dusk="a2f-recovery-codes">
                    <p class="text-sm text-fg mb-3">{{ l('security.recovery_once') }}</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 rounded border border-dashed border-field p-4 font-mono text-base text-fg">
                        <span v-for="item in recoveryCodes" :key="item">{{ item }}</span>
                    </div>
                </section>
            </AdminCard>

            <!-- What is set up -->
            <AdminCard :title="l('security.factors')">
                <p v-if="!factors.length" dusk="a2f-no-factors" class="text-sm text-muted">{{ l('security.none') }}</p>
                <ul v-else dusk="a2f-factor-list" class="divide-y divide-line">
                    <li v-for="factor in factors" :key="factor.id" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <span class="text-sm text-fg">
                            <strong>{{ l(`factors.${factor.type}`) }}</strong><template v-if="factor.name"> — {{ factor.name }}</template>
                            <br>
                            <span class="text-muted">
                                {{ l('security.added_at', { date: factor.added_at }) }}<template v-if="factor.used_at"> · {{ l('security.used_at', { date: factor.used_at }) }}</template>
                            </span>
                        </span>
                        <AdminButton variant="outline" size="sm" :disabled="busy" :dusk="`a2f-remove-${factor.id}`" @click="send('delete', factor.remove_url)">
                            {{ l('security.remove') }}<span class="sr-only"> {{ l(`factors.${factor.type}`) }} {{ factor.name }}</span>
                        </AdminButton>
                    </li>
                </ul>
            </AdminCard>

            <!-- Authenticator app -->
            <AdminCard v-if="allows('totp')" :title="l('factors.totp')" :subtitle="l('security.totp_hint')">
                <section id="totp">
                    <p v-if="errors.totp" role="alert" data-error class="mb-3 text-sm text-danger">{{ errors.totp }}</p>
                    <div v-if="totp" class="flex flex-wrap items-start gap-6">
                        <!-- Drawn by the server from the secret: an SVG of its own making. -->
                        <div dusk="a2f-qr" role="img" :aria-label="l('security.qr')" class="inline-block rounded bg-white p-2 leading-none" v-html="totp.qr"></div>
                        <form class="min-w-56 flex-1 space-y-4" @submit.prevent="send('post', urls['totp.confirm'], { name: totpName, code: totpCode })">
                            <p class="text-sm text-fg">{{ l('security.totp_manual') }}<br><span dusk="a2f-secret" class="font-mono break-all">{{ totp.secret }}</span></p>
                            <div>
                                <label for="a2f-totp-name" class="block text-sm font-medium text-fg mb-1">{{ l('security.name') }}</label>
                                <input id="a2f-totp-name" v-model="totpName" type="text" maxlength="100" :placeholder="l('security.name_totp')" :class="field">
                            </div>
                            <div>
                                <label for="a2f-totp-code" class="block text-sm font-medium text-fg mb-1">{{ l('challenge.code_label.totp') }}</label>
                                <input id="a2f-totp-code" v-model="totpCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required dusk="a2f-totp-code" :class="code">
                            </div>
                            <AdminButton type="submit" :disabled="busy" dusk="a2f-totp-confirm">{{ l('security.confirm') }}</AdminButton>
                        </form>
                    </div>
                    <AdminButton v-else variant="outline" :disabled="busy" dusk="a2f-totp-start" @click="send('post', urls['totp.start'])">
                        {{ has('totp') ? l('security.totp_another') : l('security.totp_add') }}
                    </AdminButton>
                </section>
            </AdminCard>

            <!-- Passkeys -->
            <AdminCard v-if="allows('passkey')" :title="l('factors.passkey')" :subtitle="passwordless ? l('security.passkey_hint_passwordless') : l('security.passkey_hint')">
                <section id="passkeys">
                    <form class="flex flex-wrap items-end gap-4" @submit.prevent="addPasskey">
                        <div class="min-w-48 flex-1">
                            <label for="a2f-passkey-name" class="block text-sm font-medium text-fg mb-1">{{ l('security.name') }}</label>
                            <input id="a2f-passkey-name" v-model="passkeyName" type="text" maxlength="100" :placeholder="l('security.name_passkey')" :class="field">
                        </div>
                        <AdminButton type="submit" variant="outline" :disabled="busy || !supportsPasskeys" dusk="a2f-passkey-add">{{ l('security.passkey_add') }}</AdminButton>
                    </form>
                    <p v-if="passkeyMessage || !supportsPasskeys" role="alert" data-a2f-message class="mt-3 text-sm text-danger">{{ passkeyMessage || l('passkey.unsupported') }}</p>
                </section>
            </AdminCard>

            <!-- SMS -->
            <AdminCard v-if="allows('sms')" :title="l('factors.sms')">
                <section id="sms">
                    <p v-if="errors.sms" role="alert" data-error class="mb-3 text-sm text-danger">{{ errors.sms }}</p>
                    <p v-if="errors.phone" role="alert" data-error class="mb-3 text-sm text-danger">{{ errors.phone }}</p>
                    <form v-if="sms" class="flex flex-wrap items-end gap-4" @submit.prevent="send('post', urls['sms.confirm'], { code: smsCode })">
                        <div class="min-w-48 flex-1">
                            <label for="a2f-sms-code" class="block text-sm font-medium text-fg mb-1">{{ l('security.sms_sent', { phone: sms.phone }) }}</label>
                            <input id="a2f-sms-code" v-model="smsCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required dusk="a2f-sms-code" :class="code">
                        </div>
                        <AdminButton type="submit" :disabled="busy" dusk="a2f-sms-confirm">{{ l('security.confirm') }}</AdminButton>
                    </form>
                    <form v-else class="flex flex-wrap items-end gap-4" @submit.prevent="send('post', urls['sms.start'], { phone })">
                        <div class="min-w-48 flex-1">
                            <label for="a2f-phone" class="block text-sm font-medium text-fg mb-1">{{ l('security.phone') }}</label>
                            <input id="a2f-phone" v-model="phone" type="tel" autocomplete="tel" placeholder="+1 555 0100" required dusk="a2f-phone" :class="field">
                        </div>
                        <AdminButton type="submit" variant="outline" :disabled="busy" dusk="a2f-sms-start">{{ l('security.sms_add') }}</AdminButton>
                    </form>
                </section>
            </AdminCard>

            <!-- E-mail -->
            <AdminCard v-if="allows('email') && !has('email')" :title="l('factors.email')" :subtitle="l('security.email_hint', { email })">
                <section id="email">
                    <AdminButton variant="outline" :disabled="busy" dusk="a2f-email-enable" @click="send('post', urls['email.enable'])">{{ l('security.email_add') }}</AdminButton>
                </section>
            </AdminCard>

            <!-- Recovery codes -->
            <AdminCard v-if="factors.length" :title="l('security.recovery')">
                <section id="recovery">
                    <p dusk="a2f-recovery-left" class="mb-3 text-sm text-muted">{{ recoveryLeft }}</p>
                    <AdminButton variant="outline" :disabled="busy" dusk="a2f-recovery-regenerate" @click="send('post', urls['recovery.regenerate'])">{{ l('security.recovery_regenerate') }}</AdminButton>
                </section>
            </AdminCard>

            <!-- Trusted devices -->
            <AdminCard v-if="trustedDays > 0 || devices.length" :title="l('security.devices')">
                <section id="devices">
                    <p v-if="!devices.length" class="text-sm text-muted">{{ l('security.devices_none') }}</p>
                    <template v-else>
                        <ul class="divide-y divide-line">
                            <li v-for="device in devices" :key="device.id" class="py-3 text-sm text-fg first:pt-0 last:pb-0">
                                {{ device.name || '—' }}<br>
                                <span class="text-muted">{{ device.ip }} · {{ l('security.until', { date: device.until }) }}</span>
                            </li>
                        </ul>
                        <AdminButton class="mt-4" variant="outline" :disabled="busy" dusk="a2f-devices-revoke" @click="send('delete', urls['devices.revoke'])">{{ l('security.devices_revoke') }}</AdminButton>
                    </template>
                </section>
            </AdminCard>

            <!-- Recent sign-ins -->
            <AdminCard :title="l('security.events')">
                <EventsTable :t="t" :events="events" />
            </AdminCard>
        </div>
    </AdminLayout>
</template>

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/**
 * Registering a passkey in the browser: the server's options, the
 * browser's ceremony, the answer posted back. WebAuthn speaks bytes, the
 * server's JSON speaks base64url.
 */
import { ajaxHeaders } from './csrf'

function toBytes(value) {
    const text = atob(value.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((value.length + 3) % 4))
    const bytes = new Uint8Array(text.length)

    for (let i = 0; i < text.length; i++) {
        bytes[i] = text.charCodeAt(i)
    }

    return bytes.buffer
}

function toText(buffer) {
    let text = ''

    for (const byte of new Uint8Array(buffer)) {
        text += String.fromCharCode(byte)
    }

    return btoa(text).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body),
    })
    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
        throw new Error(data.message || response.statusText)
    }

    return data
}

/**
 * Asks the server for options, lets the browser make a passkey and posts it
 * back with the extra fields (its name). Resolves with the server's answer.
 */
export async function registerPasskey(optionsUrl, storeUrl, extra = {}) {
    const { publicKey } = await post(optionsUrl, {})

    publicKey.challenge = toBytes(publicKey.challenge)
    publicKey.user.id = toBytes(publicKey.user.id)
    ;(publicKey.excludeCredentials || []).forEach((item) => { item.id = toBytes(item.id) })

    const credential = await navigator.credentials.create({ publicKey })

    return post(storeUrl, {
        ...extra,
        id: credential.id,
        rawId: toText(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: toText(credential.response.clientDataJSON),
            attestationObject: toText(credential.response.attestationObject),
        },
    })
}

<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, markRaw, ref } from 'vue'

// Import specialized field components
import WysiwygField from './WysiwygField.vue'
import ColorField from './ColorField.vue'
import SlugField from './SlugField.vue'
import TreeSelectField from './TreeSelectField.vue'
import GalleryField from './GalleryField.vue'
import RepeatableField from './RepeatableField.vue'
import ContentField from './ContentField.vue'
import BelongsToManyField from './BelongsToManyField.vue'
import MultiSelectField from './MultiSelectField.vue'
import SiteRolesField from './SiteRolesField.vue'
import MapField from './MapField.vue'
import TreeField from './TreeField.vue'
import LinkField from './LinkField.vue'
import RelationSearch from './RelationSearch.vue'
import FormBuilderField from './FormBuilderField.vue'
import FileManager from '../FileManager.vue'
import { ajaxHeaders, sessionExpiredMessage } from '../../../Support/csrf'
import { t } from '@atrium/Support/i18n'

const props = defineProps({
    field: {
        type: Object,
        required: true
    },
    modelValue: {
        type: [String, Number, Boolean, Array, Object],
        default: null
    },
    error: {
        type: String,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

// Map field types to components
const fieldComponents = {
    wysiwyg: markRaw(WysiwygField),
    color: markRaw(ColorField),
    slug: markRaw(SlugField),
    treeSelect: markRaw(TreeSelectField),
    gallery: markRaw(GalleryField),
    repeatable: markRaw(RepeatableField),
    content: markRaw(ContentField),
    belongsToMany: markRaw(BelongsToManyField),
    map: markRaw(MapField),
    tree: markRaw(TreeField),
    link: markRaw(LinkField),
    formBuilder: markRaw(FormBuilderField),
}

// Check if field has a specialized component
const hasSpecializedComponent = computed(() => {
    return props.field.type in fieldComponents
})

// These draw their own hint and error; the rest leave them to this component.
const ownsHint = computed(() => hasSpecializedComponent.value && props.field.type !== 'repeatable')

// Get the specialized component
const specializedComponent = computed(() => {
    return fieldComponents[props.field.type] || null
})

// Get input type for standard inputs
const inputType = computed(() => {
    const typeMap = {
        text: 'text',
        email: 'email',
        password: 'password',
        number: 'number',
        date: 'date',
        datetime: 'datetime-local',
        url: 'url',
        tel: 'tel',
    }
    return typeMap[props.field.type] || 'text'
})

// Value with v-model support
const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

// Date/datetime inputs accept only "YYYY-MM-DD" and "YYYY-MM-DDTHH:mm", but
// Eloquent's 'datetime' cast serialises to ISO-8601 ("2019-02-15T17:55:00.000000Z").
// Feeding that straight to the input silently renders an EMPTY box, so an
// existing publication window looks unset and is wiped on the next save.
// Normalise on read; pass the input's own value through untouched on write.
const temporalValue = computed({
    get: () => {
        const raw = value.value

        if (props.field.type !== 'date' && props.field.type !== 'datetime') {
            return raw
        }
        if (typeof raw !== 'string' || raw === '') {
            return raw
        }

        // "2019-02-15T17:55:00.000000Z" / "2019-02-15 17:55:00" -> "2019-02-15T17:55"
        const normalised = raw.replace(' ', 'T')

        return props.field.type === 'date'
            ? normalised.slice(0, 10)
            : normalised.slice(0, 16)
    },
    set: (val) => {
        value.value = val
    },
})

// Check if field is a checkbox
const isCheckbox = computed(() => props.field.type === 'checkbox')

// Read-only HTML the server drew from the record (DisplayField)
const isDisplay = computed(() => props.field.type === 'display')

// Check if field is a textarea
const isTextarea = computed(() => props.field.type === 'textarea')

// Check if field is a select
const isSelect = computed(() => props.field.type === 'select'
    // A link to one record of another module: a list of its records…
    || (props.field.type === 'belongsTo' && !props.field.searchUrl))

// …or, when they are too many for the page, a search among them.
const isRelationSearch = computed(() => props.field.type === 'belongsTo' && Boolean(props.field.searchUrl))

// Check if field is an image
const isImage = computed(() => props.field.type === 'image')

// Check if field is a file
const isFile = computed(() => props.field.type === 'file')

// Resolve a stored value into a displayable URL.
// Handles root-relative Bitrix paths (/upload/iblock/...), storage-relative
// paths (uploads/2026/07/x.jpg) and fully-qualified URLs. Anything that is not
// a non-empty string yields null instead of throwing.
function resolveUrl(val) {
    if (typeof val !== 'string' || val === '') return null
    if (/^(https?:)?\/\//i.test(val)) return val
    if (val.startsWith('/')) return val
    return '/storage/' + val
}

// Get image URL (handles both /storage/ and absolute paths like /upload/)
const imageUrl = computed(() => resolveUrl(value.value))

// Get file URL for the 'file' branch
const fileUrl = computed(() => resolveUrl(value.value))

// Filename as stored (last path segment), for the 'file' branch
const fileName = computed(() => {
    if (typeof value.value !== 'string' || value.value === '') return null
    const clean = value.value.split('?')[0].split('#')[0]
    const segment = clean.split('/').filter(Boolean).pop() || clean
    try {
        return decodeURIComponent(segment)
    } catch (e) {
        return segment
    }
})

// ------------------------------------------------------------------ uploads

const uploading = ref(false)
const uploadProgress = ref(0)
const uploadError = ref(null)

const uploadState = computed(() => {
    if (uploading.value) return 'uploading'
    return uploadError.value ? 'error' : 'idle'
})

// POST target. Prefers an explicit field prop, then the named Ziggy route
// (so a custom atrium.prefix is honoured), then the documented default.
// Always resolved relative (absolute = false) to keep the request same-origin.
const uploadUrl = computed(() => {
    if (props.field.uploadUrl) return props.field.uploadUrl

    try {
        if (typeof route === 'function') {
            return route('admin.upload', undefined, false)
        }
    } catch (e) {
        // Ziggy missing or route not registered — fall through to the default.
    }

    return '/admin/upload'
})

function parseBody(xhr) {
    if (xhr.response && typeof xhr.response === 'object') return xhr.response
    if (typeof xhr.response === 'string' && xhr.response !== '') {
        try {
            return JSON.parse(xhr.response)
        } catch (e) {
            return null
        }
    }
    return null
}

function messageFrom(body) {
    if (!body) return null
    const fileErrors = body.errors?.file
    if (Array.isArray(fileErrors) && fileErrors.length > 0) return String(fileErrors[0])
    if (typeof body.message === 'string' && body.message !== '') return body.message
    return null
}

// XMLHttpRequest rather than fetch: it is the only dependency-free way to get
// upload progress events.
function postFile(file) {
    return new Promise((resolve, reject) => {
        const formData = new FormData()
        formData.append('file', file)

        const xhr = new XMLHttpRequest()
        xhr.open('POST', uploadUrl.value, true)
        xhr.responseType = 'json'

        const headers = ajaxHeaders()
        Object.keys(headers).forEach((key) => xhr.setRequestHeader(key, headers[key]))

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && event.total > 0) {
                uploadProgress.value = Math.round((event.loaded / event.total) * 100)
            }
        }

        xhr.onerror = () => reject(new Error(t('fields.upload.network_error')))
        xhr.onabort = () => reject(new Error(t('fields.upload.aborted')))

        xhr.onload = () => {
            const status = xhr.status
            const body = parseBody(xhr)

            if (status >= 200 && status < 300) {
                if (body && typeof body.url === 'string' && body.url !== '') {
                    resolve(body)
                } else {
                    reject(new Error(t('fields.upload.no_url')))
                }
                return
            }

            if (status === 401 || status === 419) {
                reject(new Error(sessionExpiredMessage()))
                return
            }

            if (status === 413) {
                reject(new Error(t('fields.upload.too_large')))
                return
            }

            reject(new Error(messageFrom(body) || t('fields.upload.http_error', { status })))
        }

        xhr.send(formData)
    })
}

// What to store for an uploaded file.
//
// Deliberately built from the response's `path` (e.g. "uploads/2026/07/x.jpg")
// rather than its `url`. Storage::url() embeds APP_URL, which is NOT the host
// the browser is on (APP_URL is http://php inside Docker, the browser is on
// localhost:8088), so storing the url would bake an unroutable host into the
// database. `path` is host-independent, so a root-relative value works
// everywhere and matches how migrated Bitrix values look ("/upload/iblock/...").
function storedValueFor(result) {
    // A full address comes only from a remote upload disk (S3 and the like,
    // its AWS_URL often the CDN): there is no /storage/ path to build.
    if (typeof result?.url === 'string' && /^https?:\/\//i.test(result.url)) {
        return result.url
    }

    if (result && typeof result.path === 'string' && result.path !== '') {
        return '/storage/' + result.path.replace(/^\/+/, '')
    }

    // Fall back to the url only if the server gave no path.
    const url = result?.url
    if (typeof url !== 'string' || url === '') return null

    try {
        const parsed = new URL(url, window.location.origin)
        return parsed.origin === window.location.origin
            ? parsed.pathname + parsed.search
            : url
    } catch (e) {
        return url
    }
}

async function handleUpload(event) {
    const input = event.target
    const file = input.files && input.files[0]

    if (!file) return

    uploadError.value = null

    const maxSizeKb = Number(props.field.maxSize) || 0
    if (maxSizeKb > 0 && file.size > maxSizeKb * 1024) {
        uploadError.value = t('fields.upload.too_big', { max: maxSizeKb })
        input.value = ''
        return
    }

    uploading.value = true
    uploadProgress.value = 0

    try {
        const result = await postFile(file)
        value.value = storedValueFor(result)
    } catch (e) {
        uploadError.value = e?.message || t('fields.upload.failed')
    } finally {
        uploading.value = false
        // Reset the input so picking the same file again re-fires `change`.
        input.value = ''
    }
}

function clearValue() {
    value.value = null
    uploadError.value = null
}

// Media library: pick an already uploaded file instead of uploading it again.
// That is how it worked in Bitrix — an image field had both "upload" and
// "pick from media library".
const showLibrary = ref(false)

function pickFromLibrary(file) {
    // The library holds a ready root-relative path — exactly the form in which
    // migrated items store the value.
    value.value = file?.url || null
    uploadError.value = null
    showLibrary.value = false
}
</script>

<template>
    <div
        class="admin-field"
        :class="{ 'has-error': error }"
        :data-field="field.name"
        :data-field-type="field.type"
        :data-field-tab="field.tab"
        :data-field-required="field.required ? 'true' : 'false'"
    >
        <!-- Label (only for non-specialized components that don't render their own label) -->
        <label
            v-if="!isCheckbox && !hasSpecializedComponent"
            :for="field.name"
            class="block text-sm font-medium text-fg mb-1"
        >
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>

        <!-- Specialized Components (render their own labels) -->
        <component
            v-if="hasSpecializedComponent"
            :is="specializedComponent"
            v-bind="field"
            v-model="value"
            :error="error"
        />

        <!-- Checkbox -->
        <template v-else-if="isCheckbox">
            <div class="flex items-center">
                <!-- checkedValue: the checkbox stores the Bitrix string ('YES'/'Y'),
                     not a boolean — otherwise public templates comparing
                     against that string would stop finding the value -->
                <input
                    :id="field.name"
                    v-model="value"
                    type="checkbox"
                    :true-value="field.checkedValue ?? true"
                    :false-value="field.checkedValue ? null : false"
                    :disabled="field.disabled"
                    :readonly="field.readonly"
                    class="h-4 w-4 text-accent focus:ring-focus border-field rounded"
                />
                <label :for="field.name" class="ml-2 text-sm text-fg">
                    {{ field.label }}
                    <span v-if="field.required" class="text-danger">*</span>
                </label>
            </div>
        </template>

        <!-- Read-only HTML from the server; escaped there by its Blade view -->
        <div
            v-else-if="isDisplay"
            :id="field.name"
            class="admin-display text-sm text-fg"
            v-html="value"
        />

        <!-- Textarea -->
        <template v-else-if="isTextarea">
            <textarea
                :id="field.name"
                v-model="value"
                :placeholder="field.placeholder"
                :required="field.required"
                :disabled="field.disabled"
                :readonly="field.readonly"
                :rows="field.rows || 4"
                class="w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus resize-y"
                :class="{ 'border-danger': error }"
            />
        </template>

        <!-- Several values from a list -->
        <template v-else-if="field.type === 'multiSelect'">
            <MultiSelectField
                :id="field.name"
                v-model="value"
                :options="field.options"
                :placeholder="field.placeholder || $t('common.select')"
                :disabled="field.disabled"
                :error="error"
            />
        </template>

        <!-- Roles for each site apart -->
        <template v-else-if="field.type === 'siteRoles'">
            <SiteRolesField
                :id="field.name"
                v-model="value"
                :sites="field.sites"
                :roles="field.roles"
                :placeholder="field.placeholder || $t('common.select')"
                :disabled="field.disabled"
                :error="error"
            />
        </template>

        <!-- One record of another module, searched -->
        <template v-else-if="isRelationSearch">
            <RelationSearch
                v-model="value"
                :input-id="field.name"
                :search-url="field.searchUrl"
                :selected="field.selected"
                :clearable="!field.required && field.clearable !== false"
                :placeholder="field.placeholder"
                :disabled="field.disabled"
                :error="error"
            />
        </template>

        <!-- Select -->
        <template v-else-if="isSelect">
            <select
                :id="field.name"
                v-model="value"
                :required="field.required"
                :disabled="field.disabled"
                class="w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus bg-surface"
                :class="{ 'border-danger': error }"
            >
                <option v-if="!field.required" value="">
                    {{ field.placeholder || $t('common.select') }}
                </option>
                <option
                    v-for="option in field.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </template>

        <!-- Image Upload -->
        <template v-else-if="isImage">
            <div class="flex items-center space-x-4" :data-upload-state="uploadState">
                <div
                    v-if="imageUrl"
                    class="relative w-24 h-24 rounded-lg overflow-hidden border border-line shrink-0"
                >
                    <img :src="imageUrl" class="w-full h-full object-cover" />
                    <button
                        type="button"
                        @click="clearValue"
                        :disabled="uploading"
                        data-action="remove-upload"
                        :title="$t('common.delete')"
                        class="absolute top-1 right-1 p-1 bg-danger text-on-danger rounded-full hover:bg-danger-hover disabled:opacity-50"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <input
                    :id="field.name"
                    type="file"
                    :accept="field.accept || 'image/*'"
                    :disabled="field.disabled || uploading"
                    @change="handleUpload"
                    class="block w-full text-sm text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-accent-soft file:text-accent-hover hover:file:bg-accent-soft disabled:opacity-50"
                />

                <button
                    type="button"
                    @click="showLibrary = true"
                    :disabled="field.disabled || uploading"
                    data-action="pick-from-library"
                    class="shrink-0 px-3 py-2 text-sm font-medium text-fg bg-surface border border-field rounded-md hover:bg-hover disabled:opacity-50 whitespace-nowrap"
                >
                    {{ $t('fields.upload.from_library') }}
                </button>
            </div>

            <div v-if="uploading" class="mt-2">
                <div class="h-1.5 w-full bg-off rounded overflow-hidden">
                    <div
                        class="h-full bg-accent transition-all"
                        :style="{ width: uploadProgress + '%' }"
                        :data-upload-progress="uploadProgress"
                    />
                </div>
                <p class="mt-1 text-xs text-muted">{{ $t('fields.upload.progress', { percent: uploadProgress }) }}</p>
            </div>

            <p v-if="uploadError" data-upload-error class="mt-1 text-sm text-danger">
                {{ uploadError }}
            </p>
        </template>

        <!-- File Upload -->
        <template v-else-if="isFile">
            <div class="flex items-center space-x-4" :data-upload-state="uploadState">
                <div v-if="fileName" data-file-name class="flex items-center space-x-2 shrink-0">
                    <a
                        :href="fileUrl"
                        target="_blank"
                        rel="noopener"
                        :title="value"
                        class="text-sm text-accent hover:underline break-all"
                    >{{ fileName }}</a>
                    <button
                        type="button"
                        @click="clearValue"
                        :disabled="uploading"
                        data-action="remove-upload"
                        :title="$t('common.delete')"
                        class="p-1 text-danger hover:text-danger disabled:opacity-50"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <input
                    :id="field.name"
                    type="file"
                    :accept="field.accept || undefined"
                    :disabled="field.disabled || uploading"
                    @change="handleUpload"
                    class="block w-full text-sm text-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-accent-soft file:text-accent-hover hover:file:bg-accent-soft disabled:opacity-50"
                />

                <button
                    type="button"
                    @click="showLibrary = true"
                    :disabled="field.disabled || uploading"
                    data-action="pick-from-library"
                    class="shrink-0 px-3 py-2 text-sm font-medium text-fg bg-surface border border-field rounded-md hover:bg-hover disabled:opacity-50 whitespace-nowrap"
                >
                    {{ $t('fields.upload.from_library') }}
                </button>
            </div>

            <div v-if="uploading" class="mt-2">
                <div class="h-1.5 w-full bg-off rounded overflow-hidden">
                    <div
                        class="h-full bg-accent transition-all"
                        :style="{ width: uploadProgress + '%' }"
                        :data-upload-progress="uploadProgress"
                    />
                </div>
                <p class="mt-1 text-xs text-muted">{{ $t('fields.upload.progress', { percent: uploadProgress }) }}</p>
            </div>

            <p v-if="uploadError" data-upload-error class="mt-1 text-sm text-danger">
                {{ uploadError }}
            </p>
        </template>

        <!-- Standard Input -->
        <template v-else>
            <input
                :id="field.name"
                v-model="temporalValue"
                :type="inputType"
                :placeholder="field.placeholder"
                :required="field.required"
                :disabled="field.disabled"
                :readonly="field.readonly"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                class="w-full px-3 py-2 border border-field rounded-md shadow-sm focus:ring-focus focus:border-focus"
                :class="{ 'border-danger': error }"
            />
        </template>

        <!-- Hint -->
        <p v-if="field.hint && !error && !ownsHint" class="mt-1 text-sm text-muted">
            {{ field.hint }}
        </p>

        <!-- Error -->
        <p v-if="error && !ownsHint" data-error class="mt-1 text-sm text-danger">
            {{ error }}
        </p>

        <FileManager
            v-if="isImage || isFile"
            :show="showLibrary"
            :type="isImage ? 'image' : 'all'"
            @select="pickFromLibrary"
            @close="showLibrary = false"
        />
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminFieldRenderer from './AdminFieldRenderer.vue'

/**
 * One field of a record form. A translatable field shows the text of the
 * language chosen in ContentLocaleSwitcher and writes back only that
 * language; any other field passes straight through.
 */
const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object], default: null },
    errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const locale = inject('contentLocale', ref(null))
const locales = computed(() => usePage().props.contentLocales || [])
const language = computed(() => locales.value.find((item) => item.code === locale.value))
const translatable = computed(() => Boolean(props.field.translatable && language.value))

const shownField = computed(() => {
    if (!translatable.value) {
        return props.field
    }

    const field = { ...props.field }

    if (locales.value.length > 1) {
        field.label = `${field.label} (${language.value.name})`
    }

    // Only the default language is required: the others fall back to it.
    if (!language.value.default) {
        field.required = false
    }

    return field
})

const value = computed(() => translatable.value
    ? (props.modelValue?.[locale.value] ?? '')
    : props.modelValue)

const error = computed(() => translatable.value
    ? (props.errors[`${props.field.name}.${locale.value}`] ?? props.errors[props.field.name])
    : props.errors[props.field.name])

function update(next) {
    emit('update:modelValue', translatable.value
        ? { ...(props.modelValue || {}), [locale.value]: next }
        : next)
}
</script>

<template>
    <AdminFieldRenderer
        :key="translatable ? locale : 'value'"
        :field="shownField"
        :model-value="value"
        :error="error"
        :data-content-locale-field="translatable ? locale : null"
        @update:model-value="update"
    />
</template>

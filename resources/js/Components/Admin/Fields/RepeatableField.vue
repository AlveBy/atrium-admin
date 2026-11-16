<script setup>
import { computed } from 'vue'
import ContentField from './ContentField.vue'
import { t } from '@atrium/Support/i18n'

const props = defineProps({
    // Label of a single item: "Day description #1", "Departure date #2".
    // Empty — the field's label is used.
    itemLabel: {
        type: String,
        default: ''
    },
    // Modes for itemType="content" (the same as ContentField's).
    itemModes: {
        type: Array,
        default: () => ['text', 'html', 'wysiwyg']
    },
    // Editor buttons for itemType="content". Empty — ContentField uses its own
    // set; the button list is not duplicated here so the two don't diverge.
    itemToolbar: {
        type: Array,
        default: null
    },
    modelValue: {
        type: Array,
        default: () => []
    },
    name: {
        type: String,
        required: true
    },
    label: {
        type: String,
        default: ''
    },
    itemType: {
        type: String,
        default: 'text'
    },
    maxItems: {
        type: Number,
        default: 0  // 0 = unlimited
    },
    minItems: {
        type: Number,
        default: 0
    },
    // Empty — the generic "Add" is used.
    addLabel: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: ''
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const items = computed({
    get: () => Array.isArray(props.modelValue) ? props.modelValue : [],
    set: (val) => emit('update:modelValue', val)
})

const canAdd = computed(() => !props.disabled && (props.maxItems === 0 || items.value.length < props.maxItems))
const canRemove = computed(() => !props.disabled && items.value.length > props.minItems)

const inputType = computed(() => {
    const typeMap = {
        text: 'text',
        number: 'number',
        date: 'date',
        datetime: 'datetime-local',
        email: 'email',
        url: 'url',
        tel: 'tel'
    }
    return typeMap[props.itemType] || 'text'
})

// A multiple HTML property in Bitrix gives every value its own editor with the
// Text/HTML/Visual switch. A single-line input won't do for that, so such items
// are drawn as cards with a ContentField inside.
const isRichItem = computed(() => props.itemType === 'content')

const addText = computed(() => props.addLabel || t('common.add'))

function itemTitle(index) {
    const base = props.itemLabel || props.label || t('fields.repeatable.item')
    return t('fields.repeatable.item_title', { label: base, number: index + 1 })
}

function addItem() {
    if (canAdd.value) {
        items.value = [...items.value, '']
    }
}

function removeItem(index) {
    if (canRemove.value) {
        const newItems = [...items.value]
        newItems.splice(index, 1)
        items.value = newItems
    }
}

function updateItem(index, value) {
    const newItems = [...items.value]
    newItems[index] = value
    items.value = newItems
}

function moveItem(fromIndex, toIndex) {
    if (toIndex < 0 || toIndex >= items.value.length) return

    const newItems = [...items.value]
    const [removed] = newItems.splice(fromIndex, 1)
    newItems.splice(toIndex, 0, removed)
    items.value = newItems
}
</script>

<template>
    <div class="repeatable-field">
        <!-- Label -->
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-2">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <!-- Multi-line values: each has its own editor, like a multiple HTML property in Bitrix -->
        <div v-if="isRichItem" class="space-y-3">
            <div
                v-for="(item, index) in items"
                :key="index"
                :data-repeatable-item="index"
                class="border border-gray-200 rounded-lg p-3 bg-white"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ itemTitle(index) }}</span>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="moveItem(index, index - 1)"
                            :disabled="disabled || index === 0"
                            class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                            :title="$t('fields.repeatable.move_up')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="moveItem(index, index + 1)"
                            :disabled="disabled || index === items.length - 1"
                            class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                            :title="$t('fields.repeatable.move_down')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button
                            v-if="canRemove"
                            type="button"
                            @click="removeItem(index)"
                            class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                            :title="$t('common.delete')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- name is unique per index: otherwise the mode radios of all items
                     would end up in one group and switch together -->
                <ContentField
                    :model-value="item"
                    @update:model-value="updateItem(index, $event)"
                    :name="`${name}_${index}`"
                    :modes="itemModes"
                    v-bind="itemToolbar ? { toolbar: itemToolbar } : {}"
                    :disabled="disabled"
                    :rows="8"
                />
            </div>
        </div>

        <!-- Items list -->
        <div v-else class="space-y-2">
            <div
                v-for="(item, index) in items"
                :key="index"
                :data-repeatable-item="index"
                class="flex items-center gap-2"
            >
                <!-- Drag handle / reorder buttons -->
                <div class="flex flex-col gap-0.5">
                    <button
                        type="button"
                        @click="moveItem(index, index - 1)"
                        :disabled="disabled || index === 0"
                        class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                        :title="$t('fields.repeatable.move_up')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="moveItem(index, index + 1)"
                        :disabled="disabled || index === items.length - 1"
                        class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                        :title="$t('fields.repeatable.move_down')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Input field -->
                <input
                    :type="inputType"
                    :value="item"
                    @input="updateItem(index, $event.target.value)"
                    :placeholder="placeholder || $t('fields.repeatable.item_title', { label, number: index + 1 })"
                    :disabled="disabled"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                    :class="{ 'border-red-500': error }"
                />

                <!-- Remove button -->
                <button
                    v-if="canRemove"
                    type="button"
                    @click="removeItem(index)"
                    :disabled="!canRemove"
                    class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md disabled:opacity-30 disabled:cursor-not-allowed"
                    :title="$t('common.delete')"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="items.length === 0" class="text-gray-500 text-sm py-2">
            {{ $t('fields.repeatable.empty', { add: addText }) }}
        </div>

        <!-- Add button -->
        <button data-action="repeatable-add"
            v-if="canAdd"
            type="button"
            @click="addItem"
            class="mt-2 inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md border border-blue-200 transition-colors"
        >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ addText }}
        </button>

        <!-- Counter -->
        <div v-if="maxItems > 0" class="mt-1 text-xs text-gray-500">
            {{ items.length }} / {{ maxItems }}
        </div>
    </div>
</template>

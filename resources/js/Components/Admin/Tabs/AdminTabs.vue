<script setup>
import { computed } from 'vue'

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
        default: () => []
    },
    modelValue: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['update:modelValue'])

// Active tab
const activeTab = computed({
    get: () => props.modelValue || props.tabs[0]?.name || '',
    set: (value) => emit('update:modelValue', value)
})

// Icon SVG paths (Heroicons outline)
const iconPaths = {
    'document-text': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
    'search': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />',
    'cog': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
    'collection': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />',
    'puzzle': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />',
    'folder': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />'
}

const getIconPath = (icon) => iconPaths[icon] || iconPaths['document-text']
</script>

<template>
    <div class="admin-tabs">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" :aria-label="$t('fields.tabs.label')">
                <button
                    v-for="tab in tabs"
                    :key="tab.name"
                    type="button"
                    :data-tab="tab.name"
                    :data-tab-active="activeTab === tab.name ? 'true' : 'false'"
                    @click="activeTab = tab.name"
                    :class="[
                        activeTab === tab.name
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-150'
                    ]"
                >
                    <!-- Icon -->
                    <svg
                        v-if="tab.icon"
                        :class="[
                            activeTab === tab.name ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500',
                            '-ml-0.5 mr-2 h-5 w-5 transition-colors duration-150'
                        ]"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        v-html="getIconPath(tab.icon)"
                    />

                    <!-- Label -->
                    {{ tab.label }}

                    <!-- Badge (for error count) -->
                    <span
                        v-if="tab.badge"
                        :data-tab-badge="tab.name"
                        :class="[
                            activeTab === tab.name ? 'bg-blue-100 text-blue-600' : 'bg-red-100 text-red-600',
                            'ml-2 py-0.5 px-2 rounded-full text-xs font-medium'
                        ]"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="mt-6">
            <slot :activeTab="activeTab" />
        </div>
    </div>
</template>

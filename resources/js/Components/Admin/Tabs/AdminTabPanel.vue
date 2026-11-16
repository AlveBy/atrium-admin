<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    name: {
        type: String,
        required: true
    },
    activeTab: {
        type: String,
        required: true
    },
    lazy: {
        type: Boolean,
        default: false
    }
})

const isActive = computed(() => props.activeTab === props.name)

// For lazy mode - render only if was active at least once
const wasActive = ref(false)

watch(isActive, (value) => {
    if (value) wasActive.value = true
}, { immediate: true })

const shouldRender = computed(() => {
    if (!props.lazy) return true
    return wasActive.value
})
</script>

<template>
    <div
        v-if="shouldRender"
        v-show="isActive"
        class="admin-tab-panel"
        :class="{ 'is-active': isActive }"
        :data-tab-panel="name"
        :data-tab-active="isActive ? 'true' : 'false'"
        role="tabpanel"
    >
        <slot />
    </div>
</template>

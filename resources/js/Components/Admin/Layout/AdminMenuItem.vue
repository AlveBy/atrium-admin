<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    item: {
        type: Object,
        required: true
    },
    collapsed: Boolean
})

const page = usePage()
const isOpen = ref(false)

const hasChildren = computed(() => {
    return props.item.children && props.item.children.length > 0
})

// route() gives an absolute URL and page.url is a path, so compare paths.
// The dashboard sits at the panel root and is selected only there; any
// other entry also covers its sub-pages (/admin/news/5/edit → News).
const matches = (name) => {
    const path = route(name, undefined, false)
    const url = page.url.split('?')[0]
    if (url === path) return true
    return path !== route('admin.dashboard', undefined, false) && url.startsWith(path + '/')
}

const isActive = computed(() => Boolean(props.item.route) && matches(props.item.route))

const isChildActive = computed(() => {
    if (!hasChildren.value) return false
    return props.item.children.some(child => child.route && matches(child.route))
})

const toggleOpen = () => {
    if (hasChildren.value) {
        isOpen.value = !isOpen.value
    }
}

// Open the group that holds the current page, on load and after every visit.
// The sidebar outlives visits now, so a group the person opened stays open and
// one they closed is not forced open until they go to another page inside it.
watch(isChildActive, (active) => {
    if (active) {
        isOpen.value = true
    }
}, { immediate: true })

const icons = {
    home: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />`,
    users: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />`,
    settings: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`,
    folder: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />`,
    document: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />`,
    chart: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />`,
    'file-text': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />`,
    package: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />`,
    'help-circle': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />`,
    briefcase: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />`,
    star: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />`,
    tool: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />`,
    info: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />`,
    image: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />`,
    inbox: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />`,
    'shopping-cart': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />`,
    globe: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />`,
    shield: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />`,
    chevronDown: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />`,
    default: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />`
}

// The selected entry differs by weight and a 3px bar on the left, not by
// colour alone; its icon takes the text colour, the others stay muted.
const linkBase = 'flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-accent'
const activeClass = 'bg-sidebar-active text-sidebar-active-fg font-semibold shadow-[inset_3px_0_0_var(--atrium-sidebar-accent)]'
const idleClass = 'text-sidebar-fg font-medium hover:bg-sidebar-hover'

const childActive = (child) => matches(child.route)

const iconComponent = computed(() => {
    return icons[props.item.icon] || icons.default
})
</script>

<template>
    <li>
        <!-- Item with children (submenu) -->
        <template v-if="hasChildren">
            <button
                @click="toggleOpen"
                class="w-full flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200 hover:bg-sidebar-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-accent"
                :class="[
                    isChildActive ? 'text-sidebar-fg font-semibold' : 'text-sidebar-fg font-medium',
                    collapsed ? 'justify-center' : 'justify-between'
                ]"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :title="collapsed ? item.label : ''"
            >
                <span class="flex items-center">
                    <svg
                        class="w-5 h-5 flex-shrink-0 text-sidebar-muted"
                        :class="collapsed ? '' : 'mr-3'"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                        v-html="iconComponent"
                    />
                    <span v-if="!collapsed">{{ item.label }}</span>
                </span>
                <svg
                    v-if="!collapsed"
                    class="w-4 h-4 text-sidebar-muted transition-transform duration-200"
                    aria-hidden="true"
                    :class="{ 'rotate-180': isOpen }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    v-html="icons.chevronDown"
                />
            </button>

            <!-- Submenu -->
            <ul
                v-if="!collapsed"
                v-show="isOpen"
                class="mt-1 ml-4 space-y-1 border-l border-sidebar-line pl-3"
            >
                <li v-for="child in item.children" :key="child.route">
                    <Link
                        :data-menu-route="child.route"
                        :href="route(child.route)"
                        :class="[linkBase, childActive(child) ? activeClass : idleClass]"
                        :aria-current="childActive(child) ? 'page' : undefined"
                    >
                        {{ child.label }}
                    </Link>
                </li>
            </ul>
        </template>

        <!-- Simple item without children -->
        <template v-else>
            <Link
                :data-menu-route="item.route"
                :href="route(item.route)"
                :class="[linkBase, isActive ? activeClass : idleClass, collapsed ? 'justify-center' : '']"
                :aria-current="isActive ? 'page' : undefined"
                :title="collapsed ? item.label : ''"
            >
                <svg
                    class="w-5 h-5 flex-shrink-0"
                    :class="[collapsed ? '' : 'mr-3', isActive ? '' : 'text-sidebar-muted']"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                    v-html="iconComponent"
                />
                <span v-if="!collapsed">{{ item.label }}</span>
            </Link>
        </template>
    </li>
</template>

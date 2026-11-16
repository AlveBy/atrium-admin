<script setup>
/**
 * "Recent items" — the most recently changed items of one module.
 *
 * The row layout follows the Bitrix gadget
 * (bitrix/gadgets/bitrix/admin_iblock_elements): a grey date, the preview
 * picture on the left, the title linking to the edit form, "All items" below.
 */
defineProps({
    title: { type: String, default: '' },
    data: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-white rounded-lg shadow px-5 py-4">
        <h2 class="text-sm font-bold text-gray-900">{{ title || $t('dashboard.recent.title') }}</h2>

        <ul v-if="data.items.length" class="mt-3 divide-y divide-gray-100">
            <li v-for="item in data.items" :key="item.id" class="flex gap-3 py-2">
                <img
                    v-if="item.image"
                    :src="item.image"
                    alt=""
                    class="h-12 w-12 flex-none rounded object-cover bg-gray-100"
                >
                <div v-else class="h-12 w-12 flex-none rounded bg-gray-100" />

                <div class="min-w-0">
                    <div v-if="item.date" class="text-xs text-gray-400">{{ item.date }}</div>
                    <a
                        v-if="item.url"
                        :href="item.url"
                        class="text-sm text-blue-600 hover:underline line-clamp-2"
                    >{{ item.name }}</a>
                    <span v-else class="text-sm text-gray-900 line-clamp-2">{{ item.name }}</span>
                </div>
            </li>
        </ul>

        <p v-else class="mt-3 text-sm text-gray-500">{{ $t('dashboard.recent.empty') }}</p>

        <a
            v-if="data.allUrl"
            :href="data.allUrl"
            class="mt-3 inline-block text-sm text-blue-600 hover:underline"
        >{{ data.allLabel }}</a>
    </div>
</template>

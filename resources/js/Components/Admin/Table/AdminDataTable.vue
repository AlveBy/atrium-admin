<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminPagination from './AdminPagination.vue'

const props = defineProps({
    columns: {
        type: Array,
        required: true
    },
    items: {
        type: Object, // Laravel paginator
        required: true
    },
    sortBy: {
        type: String,
        default: ''
    },
    sortDirection: {
        type: String,
        default: 'asc'
    },
    loading: Boolean,
})

const emit = defineEmits(['sort', 'edit', 'delete'])

const handleSort = (column) => {
    if (!column.sortable) return

    const direction = props.sortBy === column.name && props.sortDirection === 'asc' ? 'desc' : 'asc'
    emit('sort', { column: column.name, direction })
}

// Gallery columns hold a JSON array. Binding that straight to :src yields
// src="a.jpg,b.jpg" and a broken image, so show the first entry.
const imageSrc = (value) => {
    const first = Array.isArray(value) ? (value.length ? value[0] : null) : value
    if (!first) return null
    // Gallery entries are path strings; older rows may hold the whole upload
    // response object, which would otherwise render as src="[object Object]".
    if (typeof first === 'object') return first.url ?? first.path ?? null
    return first
}

const getSortIcon = (column) => {
    if (props.sortBy !== column.name) return null
    return props.sortDirection === 'asc' ? 'up' : 'down'
}

// Колонка-ссылка: адрес лежит в соседней колонке той же строки (у заявки —
// карточка тура, с которой её оставили). Записи без адреса — обычный текст,
// иначе старые заявки превратились бы в ссылки в никуда.
const cellLink = (item, column) => {
    if (!column.urlFrom) return null

    const url = item[column.urlFrom]
    const text = item[column.name]

    // Без подписи ссылка была бы пустой и некликабельной.
    if (text === null || text === undefined || text === '') return null

    return typeof url === 'string' && url !== '' ? url : null
}

// Длинные значения обрезаются, чтобы одна строка не растягивала таблицу на
// два экрана; целиком показываем подсказкой.
const cellTitle = (item, column) => {
    if (column.type === 'image' || column.type === 'boolean') return null

    const value = item[column.name]

    return value === null || value === undefined || value === '' ? null : String(value)
}
</script>

<template>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <!--
                border-separate обязателен: Tailwind по умолчанию схлопывает
                границы таблицы, а со схлопнутыми границами Chrome не держит
                position:sticky на ячейках — колонка действий уезжала бы за
                край вместе с остальными.
            -->
            <table class="min-w-full border-separate border-spacing-0">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.name"
                            :data-column="column.name"
                            :data-sortable="column.sortable ? 'true' : 'false'"
                            class="border-b border-gray-200 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap"
                            :class="{ 'cursor-pointer hover:bg-gray-100': column.sortable }"
                            :style="column.width ? { width: column.width } : {}"
                            @click="handleSort(column)"
                        >
                            <div class="flex items-center space-x-1">
                                <span>{{ column.label }}</span>
                                <template v-if="column.sortable">
                                    <svg v-if="getSortIcon(column) === 'up'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                    <svg v-else-if="getSortIcon(column) === 'down'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </template>
                            </div>
                        </th>
                        <!--
                            Действия прибиты к правому краю: колонок в списке
                            бывает много (у заявок — телефон, даты, услуга), и
                            без этого «Редактировать» уезжало за пределы экрана,
                            как и жаловался заказчик. В Битриксе кнопки правки
                            видны всегда.
                        -->
                        <th class="sticky right-0 z-10 border-b border-l border-gray-200 bg-gray-50 px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Действия
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <tr v-if="loading">
                        <td :colspan="columns.length + 1" class="px-6 py-12 text-center">
                            <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                        </td>
                    </tr>
                    <tr v-else-if="!items.data?.length">
                        <td :colspan="columns.length + 1" class="px-6 py-12 text-center text-gray-500">
                            Нет данных
                        </td>
                    </tr>
                    <tr v-else v-for="item in items.data" :key="item.id" :data-row-id="item.id" class="bg-white hover:bg-gray-50">
                        <td
                            v-for="column in columns"
                            :key="column.name"
                            class="border-b border-gray-200 px-3 py-2 whitespace-nowrap text-sm max-w-xs truncate"
                            :title="cellTitle(item, column)"
                        >
                            <slot :name="`cell-${column.name}`" :item="item" :value="item[column.name]">
                                <!-- Boolean column -->
                                <template v-if="column.type === 'boolean'">
                                    <span v-if="item[column.name]" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Да
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Нет
                                    </span>
                                </template>
                                <!-- Image column -->
                                <template v-else-if="column.type === 'image'">
                                    <img
                                        v-if="imageSrc(item[column.name])"
                                        :src="imageSrc(item[column.name])"
                                        :alt="column.label"
                                        class="h-10 w-10 rounded object-cover"
                                    />
                                    <span v-else class="text-gray-400">—</span>
                                </template>
                                <!-- Date column -->
                                <template v-else-if="column.type === 'date'">
                                    {{ item[column.name] ? new Date(item[column.name]).toLocaleDateString('ru-RU') : '—' }}
                                </template>
                                <!-- Default text -->
                                <template v-else>
                                    <a
                                        v-if="cellLink(item, column)"
                                        :href="cellLink(item, column)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-blue-600 hover:text-blue-900 hover:underline"
                                    >{{ item[column.name] }}</a>
                                    <span v-else class="text-gray-900">{{ item[column.name] ?? '—' }}</span>
                                </template>
                            </slot>
                        </td>
                        <td class="sticky right-0 z-10 border-b border-l border-gray-200 bg-inherit px-3 py-2 whitespace-nowrap text-right text-sm font-medium">
                            <slot name="actions" :item="item">
                                <button
                                    data-action="edit"
                                    @click="$emit('edit', item)"
                                    class="inline-flex items-center rounded p-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-900"
                                    title="Редактировать"
                                    aria-label="Редактировать"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    data-action="delete"
                                    @click="$emit('delete', item)"
                                    class="ml-1 inline-flex items-center rounded p-1.5 text-red-600 hover:bg-red-50 hover:text-red-900"
                                    title="Удалить"
                                    aria-label="Удалить"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <AdminPagination v-if="items.total > items.per_page" :paginator="items" />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import AdminButton from '../../../Components/Admin/UI/AdminButton.vue'
import AdminDataTable from '../../../Components/Admin/Table/AdminDataTable.vue'
import AdminModal from '../../../Components/Admin/UI/AdminModal.vue'

const props = defineProps({
    routeName: String,
    createLabel: String,
    items: Object,
    columns: Array,
    filters: Object,
    title: String,
})

const search = ref(props.filters.search || '')
const showDeleteModal = ref(false)
const itemToDelete = ref(null)

const handleSearch = () => {
    router.get(route(`${props.routeName}.index`), { search: search.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const handleSort = ({ column, direction }) => {
    router.get(route(`${props.routeName}.index`), {
        ...props.filters,
        sort: column,
        direction: direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const handleEdit = (item) => {
    router.visit(route(`${props.routeName}.edit`, item.id))
}

const confirmDelete = (item) => {
    itemToDelete.value = item
    showDeleteModal.value = true
}

const handleDelete = () => {
    if (itemToDelete.value) {
        router.delete(route(`${props.routeName}.destroy`, itemToDelete.value.id), {
            onSuccess: () => {
                showDeleteModal.value = false
                itemToDelete.value = null
            }
        })
    }
}
</script>

<template>
    <AdminLayout :title="title">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 :data-page-title="routeName" class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Всего записей: {{ items.total }}
                </p>
            </div>
            <AdminButton data-action="create" :href="route(`${props.routeName}.create`)">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Создать
            </AdminButton>
        </div>

        <div class="mb-6">
            <div class="flex gap-4">
                <div class="flex-1 max-w-md">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Поиск..."
                        data-search-input="true"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                        @keyup.enter="handleSearch"
                    />
                </div>
                <AdminButton data-action="search" @click="handleSearch" variant="secondary">
                    Найти
                </AdminButton>
            </div>
        </div>

        <AdminDataTable
            :columns="columns"
            :items="items"
            :sort-by="filters.sort"
            :sort-direction="filters.direction"
            @sort="handleSort"
            @edit="handleEdit"
            @delete="confirmDelete"
        />

        <AdminModal
            :show="showDeleteModal"
            title="Подтверждение удаления"
            @close="showDeleteModal = false"
        >
            <p class="text-gray-600">
                Вы уверены, что хотите удалить эту запись?
            </p>

            <template #footer>
                <AdminButton data-action="delete-cancel" variant="secondary" @click="showDeleteModal = false">
                    Отмена
                </AdminButton>
                <AdminButton data-action="delete-confirm" variant="danger" @click="handleDelete">
                    Удалить
                </AdminButton>
            </template>
        </AdminModal>
    </AdminLayout>
</template>

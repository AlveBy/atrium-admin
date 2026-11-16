<script setup>
/**
 * Раздел «API-токены».
 *
 * Сам раздел живёт в пакете admin-panel, здесь только раскладка.
 */
import AdminLayout from '@atrium/Layouts/AdminLayout.vue'
import ApiTokensManager from '@atrium/Components/Admin/ApiTokens/ApiTokensManager.vue'

defineProps({
    tokens: Array,
    modules: Array,
    plainToken: String,
    docsUrl: String,
    openApiUrl: String,
})
</script>

<template>
    <AdminLayout title="API-токены">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">API-токены</h1>
            <p class="mt-1 text-sm text-gray-600">
                Ключи для внешних программ: агент или другой сайт работает с модулями через API от вашего имени и только там, где вы разрешили.
            </p>
        </div>

        <ApiTokensManager
            :tokens="tokens"
            :modules="modules"
            :plain-token="plainToken"
            :docs-url="docsUrl"
            :open-api-url="openApiUrl"
        />
    </AdminLayout>
</template>

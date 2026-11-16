<script setup>
/**
 * The "API tokens" section.
 *
 * The section itself lives in ApiTokensManager; this page is only the layout.
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
    <AdminLayout :title="$t('menu.api_tokens')">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ $t('menu.api_tokens') }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $t('account.api_tokens.subtitle') }}
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

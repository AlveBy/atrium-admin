<script setup>
/**
 * Раздел «API-токены»: выпуск, разовый показ ключа, отзыв.
 *
 * Права выдаются по модулям: «читать» и «менять». Отметка «менять» без
 * «читать» агенту бесполезна (он не найдёт, что правит), поэтому вторая
 * тянет за собой первую.
 */
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminButton from '../UI/AdminButton.vue'
import AdminCard from '../UI/AdminCard.vue'
import AdminModal from '../UI/AdminModal.vue'

const props = defineProps({
    tokens: { type: Array, required: true },
    modules: { type: Array, required: true },
    plainToken: { type: String, default: null },
    docsUrl: { type: String, required: true },
    openApiUrl: { type: String, required: true },
})

const form = useForm({
    name: '',
    abilities: [],
    expires_at: '',
})

const copied = ref(false)
const tokenToRevoke = ref(null)

const has = (ability) => form.abilities.includes(ability)

const toggle = (key, mode) => {
    const ability = `${key}:${mode}`
    const set = new Set(form.abilities)

    if (set.has(ability)) {
        set.delete(ability)
        if (mode === 'read') set.delete(`${key}:write`)
    } else {
        set.add(ability)
        if (mode === 'write') set.add(`${key}:read`)
    }

    form.abilities = [...set]
}

const selectAll = (mode) => {
    const set = new Set(form.abilities)
    for (const module of props.modules) {
        set.add(`${module.key}:read`)
        if (mode === 'write') set.add(`${module.key}:write`)
    }
    form.abilities = [...set]
}

const submit = () => {
    // Без preserveScroll: ключ показывается один раз, в плашке наверху, и
    // со страницей, оставшейся внизу у кнопки, его легко не заметить.
    form.post(route('admin.api-tokens.store'), {
        onSuccess: () => form.reset(),
    })
}

const copyToken = async () => {
    await navigator.clipboard.writeText(props.plainToken)
    copied.value = true
}

const revoke = () => {
    router.delete(route('admin.api-tokens.destroy', tokenToRevoke.value.id), {
        preserveScroll: true,
        onFinish: () => { tokenToRevoke.value = null },
    })
}

const moduleLabel = (ability) => {
    const [key, mode] = ability.split(':')
    const module = props.modules.find((m) => m.key === key)
    return `${module ? module.label : key}: ${mode === 'write' ? 'менять' : 'читать'}`
}
</script>

<template>
    <div class="space-y-6">
        <div v-if="plainToken" data-api-token-plain class="p-4 bg-yellow-50 border-2 border-yellow-500 rounded-lg">
            <p class="font-medium text-gray-900">Новый токен. Скопируйте его сейчас: после ухода со страницы он больше не покажется.</p>
            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                <code class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded font-mono text-sm break-all">{{ plainToken }}</code>
                <AdminButton variant="outline" @click="copyToken">{{ copied ? 'Скопировано' : 'Копировать' }}</AdminButton>
            </div>
        </div>

        <AdminCard title="Документация" subtitle="Открывается без токена. Ссылку можно дать программисту или ИИ-агенту: там все модули, поля и допустимые значения.">
            <ul class="space-y-1 text-sm">
                <li>Описание: <a :href="docsUrl" target="_blank" class="text-blue-700 underline break-all">{{ docsUrl }}</a></li>
                <li>Схема OpenAPI: <a :href="openApiUrl" target="_blank" class="text-blue-700 underline break-all">{{ openApiUrl }}</a></li>
            </ul>
        </AdminCard>

        <AdminCard title="Выпустить токен">
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="token-name" class="block text-sm font-medium text-gray-700">Название</label>
                        <input
                            id="token-name"
                            v-model="form.name"
                            type="text"
                            placeholder="Например: ИИ-агент туров"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="token-expires" class="block text-sm font-medium text-gray-700">Действует до (необязательно)</label>
                        <input
                            id="token-expires"
                            v-model="form.expires_at"
                            type="date"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        />
                        <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600">{{ form.errors.expires_at }}</p>
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-gray-700">Права</span>
                        <div class="flex gap-2">
                            <AdminButton type="button" size="sm" variant="outline" @click="selectAll('read')">Читать всё</AdminButton>
                            <AdminButton type="button" size="sm" variant="outline" @click="selectAll('write')">Всё</AdminButton>
                            <AdminButton type="button" size="sm" variant="outline" @click="form.abilities = []">Снять</AdminButton>
                        </div>
                    </div>
                    <p v-if="form.errors.abilities" class="mt-1 text-sm text-red-600">{{ form.errors.abilities }}</p>

                    <div class="mt-2 max-h-96 overflow-y-auto border border-gray-200 rounded-md">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700">Модуль</th>
                                    <th class="px-3 py-2 w-24 text-center font-medium text-gray-700">Читать</th>
                                    <th class="px-3 py-2 w-24 text-center font-medium text-gray-700">Менять</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="module in modules" :key="module.key">
                                    <td class="px-3 py-1.5">
                                        {{ module.label }}
                                        <span class="ml-1 text-xs text-gray-500 font-mono">{{ module.key }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <input type="checkbox" class="h-4 w-4" :aria-label="`${module.label}: читать`" :checked="has(`${module.key}:read`)" @change="toggle(module.key, 'read')" />
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <input type="checkbox" class="h-4 w-4" :aria-label="`${module.label}: менять`" :checked="has(`${module.key}:write`)" @change="toggle(module.key, 'write')" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end">
                    <AdminButton type="submit" :loading="form.processing" :disabled="form.processing">Выпустить токен</AdminButton>
                </div>
            </form>
        </AdminCard>

        <AdminCard title="Выпущенные токены" no-padding>
            <p v-if="tokens.length === 0" class="p-6 text-sm text-gray-600">Токенов пока нет.</p>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-700">Название</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-700">Права</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-700">Выпустил</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-700">Последний вызов</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-700">Срок</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="token in tokens" :key="token.id" data-api-token-row>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ token.name }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                <details>
                                    <summary class="cursor-pointer">{{ token.abilities.length }} прав</summary>
                                    <ul class="mt-1 space-y-0.5">
                                        <li v-for="ability in token.abilities" :key="ability">{{ moduleLabel(ability) }}</li>
                                    </ul>
                                </details>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ token.owner }}<div class="text-xs text-gray-500">{{ token.created_at }}</div></td>
                            <td class="px-4 py-2 text-gray-700">{{ token.last_used_at || 'не вызывался' }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                <template v-if="token.expires_at">
                                    до {{ token.expires_at }}<span v-if="token.expired" class="font-medium"> (истёк)</span>
                                </template>
                                <template v-else>бессрочно</template>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <AdminButton size="sm" variant="danger" @click="tokenToRevoke = token">Отозвать</AdminButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminCard>

        <AdminModal :show="tokenToRevoke !== null" title="Отозвать токен" @close="tokenToRevoke = null">
            <p class="text-gray-600">
                Программа с токеном <strong>{{ tokenToRevoke?.name }}</strong> сразу перестанет получать доступ.
            </p>
            <template #footer>
                <AdminButton variant="secondary" @click="tokenToRevoke = null">Отмена</AdminButton>
                <AdminButton variant="danger" @click="revoke">Отозвать</AdminButton>
            </template>
        </AdminModal>
    </div>
</template>

<template>
  <teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center"
      @keydown.escape="close"
    >
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black bg-opacity-50" @click="close" />

      <!-- Modal -->
      <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col mx-4">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">{{ $t('files.title') }}</h2>
          <button type="button" @click="close" class="p-1 text-gray-400 hover:text-gray-600 rounded">
            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>

        <!-- Toolbar -->
        <div class="flex items-center gap-3 px-6 py-3 border-b border-gray-100 bg-gray-50">
          <!-- Upload button -->
          <button
            type="button"
            @click="triggerUpload"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            {{ $t('common.upload') }}
          </button>

          <input
            ref="fileInput"
            type="file"
            :accept="acceptString"
            multiple
            class="hidden"
            data-role="library-upload"
            @change="handleFileSelect"
          />

          <!-- Type filter -->
          <div class="flex items-center gap-1 ml-2">
            <button
              v-for="t in typeFilters"
              :key="t.value"
              type="button"
              @click="filterType = t.value"
              class="px-3 py-1.5 text-sm rounded-md transition-colors"
              :class="filterType === t.value
                ? 'bg-indigo-100 text-indigo-700 font-medium'
                : 'text-gray-600 hover:bg-gray-200'"
            >
              {{ $t(t.label) }}
            </button>
          </div>

          <!-- Collections -->
          <select
            v-if="collections.length"
            v-model="selectedCollection"
            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 max-w-56"
          >
            <option :value="0">{{ $t('files.all_collections') }}</option>
            <option v-for="c in collections" :key="c.id" :value="c.id">
              {{ c.name }} ({{ c.count }})
            </option>
          </select>

          <!-- Search -->
          <div class="ml-auto relative">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="$t('files.search_placeholder')"
              class="w-56 pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
              @input="debouncedSearch"
            />
            <svg class="absolute left-2.5 top-2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <!-- Upload progress -->
        <div v-if="uploading" class="px-6 py-2 bg-indigo-50 border-b border-indigo-100">
          <div class="flex items-center gap-3">
            <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm text-indigo-700">{{ $t('files.uploading') }}</span>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="errorMessage" class="px-6 py-2 bg-red-50 border-b border-red-100">
          <div class="flex items-center justify-between">
            <span class="text-sm text-red-700">{{ errorMessage }}</span>
            <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-600">
              <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Files grid -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loading && !files.length" class="flex items-center justify-center py-16">
            <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>

          <div v-else-if="!files.length" class="flex flex-col items-center justify-center py-16 text-gray-400">
            <svg class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <p class="text-sm">{{ $t('files.empty') }}</p>
          </div>

          <div v-else class="grid grid-cols-4 sm:grid-cols-6 gap-3">
            <div
              v-for="file in files"
              :key="file.id"
              @click="selectFile(file)"
              @dblclick="insertFile(file)"
              class="group relative flex flex-col items-center p-2 rounded-lg border-2 cursor-pointer transition-all"
              :class="selectedFile?.id === file.id
                ? 'border-indigo-500 bg-indigo-50'
                : 'border-transparent hover:border-gray-300 hover:bg-gray-50'"
            >
              <!-- Preview -->
              <div class="w-full aspect-square rounded-md overflow-hidden bg-gray-100 flex items-center justify-center mb-2">
                <img
                  v-if="file.is_image"
                  :src="file.url"
                  :alt="file.filename"
                  class="w-full h-full object-cover"
                  loading="lazy"
                />
                <div v-else class="flex flex-col items-center justify-center text-gray-400">
                  <svg v-if="isDocFile(file)" class="w-10 h-10 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
                    <path d="M8 12h8v2H8zm0 4h8v2H8zm0-8h3v2H8z"/>
                  </svg>
                  <svg v-else-if="isPdfFile(file)" class="w-10 h-10 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
                    <path d="M10.5 11c-.3 0-.5.2-.5.5v5c0 .3.2.5.5.5s.5-.2.5-.5V15h1.5c1.1 0 2-.9 2-2s-.9-2-2-2h-2zm2 3h-1.5v-2h1.5c.6 0 1 .4 1 1s-.4 1-1 1z"/>
                  </svg>
                  <svg v-else-if="isExcelFile(file)" class="w-10 h-10 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
                    <path d="M8 12l2.5 3L8 18h1.5l1.75-2.25L13 18h1.5l-2.5-3 2.5-3H13l-1.75 2.25L9.5 12H8z"/>
                  </svg>
                  <svg v-else class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <span class="text-xs mt-1 font-medium uppercase">{{ file.extension }}</span>
                </div>
              </div>

              <!-- Filename -->
              <p class="text-xs text-gray-600 text-center truncate w-full" :title="file.filename">
                {{ file.filename }}
              </p>

              <!-- Delete button -->
              <button
                type="button"
                @click.stop="deleteFile(file)"
                class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
                :title="$t('common.delete')"
              >
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Load more -->
          <div v-if="hasMore" class="flex justify-center mt-6">
            <button
              type="button"
              @click="loadMore"
              class="px-4 py-2 text-sm text-indigo-600 border border-indigo-300 rounded-lg hover:bg-indigo-50 transition-colors"
              :disabled="loading"
            >
              {{ loading ? $t('common.loading') : $t('files.load_more') }}
            </button>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
          <!-- Selected file info -->
          <div v-if="selectedFile" class="flex items-center gap-3 text-sm text-gray-600 min-w-0 flex-1 mr-4">
            <span class="truncate">{{ selectedFile.filename }}</span>
            <span class="text-gray-400 flex-shrink-0">{{ formatSize(selectedFile.size) }}</span>
          </div>
          <div v-else class="text-sm text-gray-400">
            {{ $t('files.select_hint') }}
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              type="button"
              @click="close"
              class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              type="button"
              data-action="library-insert"
              @click="insertSelected"
              :disabled="!selectedFile"
              class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ $t('files.insert') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { ajaxHeaders, responseErrorMessage } from '../../Support/csrf'
import { t } from '@atrium/Support/i18n'

const props = defineProps({
  show: { type: Boolean, default: false },
  type: { type: String, default: 'all' }, // all, image, document
  uploadUrl: { type: String, default: '/admin/upload' },
  listUrl: { type: String, default: '/admin/filemanager' },
  deleteUrl: { type: String, default: '/admin/filemanager' },
})

const emit = defineEmits(['select', 'close'])

const fileInput = ref(null)
const files = ref([])
const selectedFile = ref(null)
const loading = ref(false)
const uploading = ref(false)
const errorMessage = ref('')
const filterType = ref(props.type)
const collections = ref([])
const selectedCollection = ref(0)
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const hasMore = ref(false)
let searchTimeout = null

const typeFilters = [
  { value: 'all', label: 'files.filters.all' },
  { value: 'image', label: 'files.filters.images' },
  { value: 'document', label: 'files.filters.documents' },
]

const acceptString = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx,.xls,.xlsx'

async function loadFiles(append = false) {
  loading.value = true
  try {
    const params = new URLSearchParams({
      type: filterType.value,
      search: searchQuery.value,
      collection: String(selectedCollection.value || ''),
      page: String(currentPage.value),
    })

    const response = await fetch(`${props.listUrl}?${params}`, {
      headers: { 'Accept': 'application/json' },
    })

    if (!response.ok) throw new Error('Failed to load files')

    const data = await response.json()

    if (append) {
      files.value = [...files.value, ...data.files]
    } else {
      files.value = data.files
    }

    collections.value = data.collections || []
    lastPage.value = data.last_page
    hasMore.value = currentPage.value < data.last_page
  } catch (e) {
    errorMessage.value = t('files.errors.list_failed')
  } finally {
    loading.value = false
  }
}

function loadMore() {
  currentPage.value++
  loadFiles(true)
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadFiles()
  }, 300)
}

watch([filterType, selectedCollection], () => {
  currentPage.value = 1
  selectedFile.value = null
  loadFiles()
})

watch(() => props.show, (val) => {
  if (val) {
    filterType.value = props.type
    selectedCollection.value = 0
    currentPage.value = 1
    selectedFile.value = null
    searchQuery.value = ''
    errorMessage.value = ''
    loadFiles()
  }
})

function triggerUpload() {
  fileInput.value?.click()
}

async function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files)
  if (!selectedFiles.length) return

  uploading.value = true
  errorMessage.value = ''

  for (const file of selectedFiles) {
    await uploadSingleFile(file)
  }

  uploading.value = false
  event.target.value = ''

  // Reload list to show new files
  currentPage.value = 1
  await loadFiles()
}

async function uploadSingleFile(file) {
  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await fetch(props.uploadUrl, {
      method: 'POST',
      body: formData,
      headers: ajaxHeaders(),
    })

    if (!response.ok) {
      throw new Error(await responseErrorMessage(response, t('files.errors.upload_failed', { name: file.name })))
    }

    const result = await response.json()

    // Auto-select the newly uploaded file
    selectedFile.value = result
  } catch (e) {
    errorMessage.value = e.message || t('files.errors.upload_failed', { name: file.name })
  }
}

async function deleteFile(file) {
  if (!confirm(t('files.remove_confirm', { name: file.filename }))) return

  try {
    const response = await fetch(props.deleteUrl, {
      method: 'DELETE',
      headers: ajaxHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ id: file.id, path: file.path }),
    })

    if (!response.ok) {
      throw new Error(await responseErrorMessage(response, t('files.errors.remove_failed', { name: file.filename })))
    }

    files.value = files.value.filter(f => f.id !== file.id)

    if (selectedFile.value?.id === file.id) {
      selectedFile.value = null
    }
  } catch (e) {
    errorMessage.value = e.message || t('files.errors.delete_failed')
  }
}

function selectFile(file) {
  selectedFile.value = file
}

function insertFile(file) {
  emit('select', file)
  close()
}

function insertSelected() {
  if (selectedFile.value) {
    emit('select', selectedFile.value)
    close()
  }
}

function close() {
  emit('close')
}

function isDocFile(file) {
  return ['doc', 'docx'].includes(file.extension)
}

function isPdfFile(file) {
  return file.extension === 'pdf'
}

function isExcelFile(file) {
  return ['xls', 'xlsx'].includes(file.extension)
}

function formatSize(bytes) {
  if (!bytes) return ''
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) {
    size /= 1024
    i++
  }
  return Math.round(size * 100) / 100 + ' ' + units[i]
}

// Handle escape key
function handleKeydown(e) {
  if (e.key === 'Escape' && props.show) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  clearTimeout(searchTimeout)
})
</script>
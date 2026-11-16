<template>
  <div class="tree-select-field">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="relative">
      <button
        type="button"
        @click="toggleDropdown"
        class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        :class="{ 'border-red-500': error }"
      >
        <span v-if="selectedLabel" class="block truncate">{{ selectedLabel }}</span>
        <span v-else class="block truncate text-gray-400">{{ placeholder || $t('common.select') }}</span>
        <span class="absolute inset-y-0 right-0 flex items-center pr-2">
          <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </span>
      </button>

      <div
        v-if="isOpen"
        class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
      >
        <div v-if="searchable" class="px-3 py-2">
          <input
            v-model="searchQuery"
            type="text"
            class="w-full border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
            :placeholder="$t('fields.tree.search_placeholder')"
          />
        </div>

        <!-- "Top level" is an ordinary list row, not the absence of a choice:
             without it, it is unclear that a section may be nested nowhere. -->
        <div
          v-if="showRootOption"
          class="flex items-center px-3 py-2 cursor-pointer hover:bg-gray-100 border-b"
          :class="{ 'bg-indigo-50': isRootSelected }"
          data-tree-value=""
          :data-tree-selected="isRootSelected ? 'true' : 'false'"
          @click="selectRoot"
        >
          <span class="w-6"></span>
          <span class="flex-1 truncate" :class="{ 'font-medium text-indigo-600': isRootSelected }">
            {{ rootLabel }}
          </span>
          <svg
            v-if="isRootSelected"
            class="h-5 w-5 text-indigo-600"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
          </svg>
        </div>

        <div v-if="clearable && !rootLabel && modelValue" class="px-3 py-2 border-b">
          <button
            type="button"
            @click="clear"
            class="text-sm text-gray-500 hover:text-gray-700"
          >
            {{ $t('fields.clear') }}
          </button>
        </div>

        <tree-node
          v-for="node in filteredOptions"
          :key="node.value"
          :node="node"
          :selected-value="modelValue"
          :expanded-nodes="expandedNodes"
          @select="selectNode"
          @toggle="toggleNode"
        />
      </div>
    </div>

    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import TreeNode from './TreeNode.vue';

const props = defineProps({
  modelValue: [String, Number, Array],
  options: { type: Array, default: () => [] },
  label: String,
  placeholder: String,
  required: Boolean,
  searchable: { type: Boolean, default: true },
  clearable: { type: Boolean, default: true },
  multiple: Boolean,
  expandAll: Boolean,
  // Label of the "nothing selected" row at the top of the list. Empty — no such row.
  rootLabel: String,
  hint: String,
  error: String,
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const expandedNodes = ref(new Set());

const filteredOptions = computed(() => {
  if (!searchQuery.value) return props.options;

  const query = searchQuery.value.toLowerCase();
  return filterTree(props.options, query);
});

/**
 * An empty value means "top level", not "not chosen yet": that is the case for
 * a new section, and the button label should say so plainly.
 */
const isRootSelected = computed(
  () => props.modelValue === null || props.modelValue === undefined || props.modelValue === ''
);

const showRootOption = computed(
  () => Boolean(props.rootLabel) && !props.multiple && matchesSearch(props.rootLabel)
);

const selectedLabel = computed(() => {
  if (isRootSelected.value) return props.rootLabel || '';

  const node = findNode(props.options, props.modelValue);
  return node ? node.label : '';
});

function matchesSearch(label) {
  if (!searchQuery.value) return true;

  return String(label).toLowerCase().includes(searchQuery.value.toLowerCase());
}

function selectRoot() {
  emit('update:modelValue', null);
  isOpen.value = false;
}

function filterTree(nodes, query) {
  return nodes.reduce((acc, node) => {
    const matches = node.label.toLowerCase().includes(query);
    const children = node.children ? filterTree(node.children, query) : [];

    if (matches || children.length) {
      acc.push({
        ...node,
        children: children.length ? children : node.children,
      });
    }

    return acc;
  }, []);
}

function findNode(nodes, value) {
  for (const node of nodes) {
    if (node.value === value) return node;
    if (node.children) {
      const found = findNode(node.children, value);
      if (found) return found;
    }
  }
  return null;
}

function toggleDropdown() {
  isOpen.value = !isOpen.value;
}

function selectNode(value) {
  emit('update:modelValue', value);
  if (!props.multiple) {
    isOpen.value = false;
  }
}

function toggleNode(value) {
  if (expandedNodes.value.has(value)) {
    expandedNodes.value.delete(value);
  } else {
    expandedNodes.value.add(value);
  }
}

function clear() {
  emit('update:modelValue', null);
  isOpen.value = false;
}

function handleClickOutside(event) {
  if (!event.target.closest('.tree-select-field')) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);

  if (props.expandAll) {
    const expandAllNodes = (nodes) => {
      nodes.forEach(node => {
        if (node.children?.length) {
          expandedNodes.value.add(node.value);
          expandAllNodes(node.children);
        }
      });
    };
    expandAllNodes(props.options);
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

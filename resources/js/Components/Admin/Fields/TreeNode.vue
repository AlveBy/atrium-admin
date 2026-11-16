<template>
  <div class="tree-node">
    <div
      class="flex items-center px-3 py-2 cursor-pointer hover:bg-hover"
      :class="{
        'bg-accent-soft': isSelected,
        'pl-6': depth === 1,
        'pl-9': depth === 2,
        'pl-12': depth >= 3,
      }"
      :data-tree-value="node.value"
      :data-tree-selected="isSelected ? 'true' : 'false'"
      @click="select"
    >
      <button
        v-if="hasChildren"
        type="button"
        class="mr-2 text-muted hover:text-fg focus:outline-none"
        @click.stop="toggle"
      >
        <svg
          class="h-4 w-4 transform transition-transform"
          :class="{ 'rotate-90': isExpanded }"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
      <span v-else class="w-6"></span>

      <span class="flex-1 truncate" :class="{ 'font-medium text-accent': isSelected }">
        {{ node.label }}
      </span>

      <svg
        v-if="isSelected"
        class="h-5 w-5 text-accent"
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

    <div v-if="hasChildren && isExpanded">
      <tree-node
        v-for="child in node.children"
        :key="child.value"
        :node="child"
        :depth="depth + 1"
        :selected-value="selectedValue"
        :expanded-nodes="expandedNodes"
        @select="$emit('select', $event)"
        @toggle="$emit('toggle', $event)"
      />
    </div>
  </div>
</template>

<script setup>
/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

import { computed } from 'vue';

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  selectedValue: [String, Number, Array],
  expandedNodes: { type: Set, default: () => new Set() },
});

const emit = defineEmits(['select', 'toggle']);

const hasChildren = computed(() => props.node.children?.length > 0);

const isExpanded = computed(() => props.expandedNodes.has(props.node.value));

const isSelected = computed(() => {
  if (Array.isArray(props.selectedValue)) {
    return props.selectedValue.includes(props.node.value);
  }
  return props.selectedValue === props.node.value;
});

function select() {
  emit('select', props.node.value);
}

function toggle() {
  emit('toggle', props.node.value);
}
</script>

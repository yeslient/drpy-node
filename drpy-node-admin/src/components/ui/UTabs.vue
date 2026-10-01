<script setup>
defineProps({
  tabs: { type: Array, required: true }, // [{ key, label, count? }]
  modelValue: { type: [String, Number], required: true },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="flex items-center gap-1 border-b border-line overflow-x-auto hide-scrollbar" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      role="tab"
      :aria-selected="modelValue === tab.key"
      class="relative px-3.5 py-2.5 text-[13px] whitespace-nowrap transition-colors min-h-[40px]"
      :class="modelValue === tab.key ? 'text-accent font-medium' : 'text-mid hover:text-hi'"
      @click="$emit('update:modelValue', tab.key)"
    >
      {{ tab.label }}
      <span v-if="tab.count !== undefined" class="ml-1 font-mono text-2xs text-low">{{ tab.count }}</span>
      <span v-if="modelValue === tab.key" class="absolute left-2 right-2 -bottom-px h-0.5 bg-accent rounded-full" />
    </button>
  </div>
</template>

<style scoped>
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>

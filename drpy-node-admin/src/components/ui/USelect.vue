<script setup>
defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] }, // [{label, value}] 或原始值数组
  disabled: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="relative">
    <select
      :value="modelValue"
      :disabled="disabled"
      class="w-full appearance-none bg-sunken border border-line rounded-ctrl text-hi focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent/60 transition-colors disabled:opacity-50 pr-8"
      :class="size === 'sm' ? 'h-8 pl-2.5 text-xs' : 'h-10 pl-3 text-[13px]'"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-for="opt in options" :key="String(opt.value ?? opt)" :value="opt.value ?? opt">
        {{ opt.label ?? opt }}
      </option>
    </select>
    <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-low" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </div>
</template>

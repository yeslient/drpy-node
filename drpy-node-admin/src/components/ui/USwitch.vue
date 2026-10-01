<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :disabled="disabled"
    class="relative inline-flex shrink-0 h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 disabled:opacity-40"
    :class="modelValue ? 'bg-accent' : 'bg-low/30'"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
      :class="modelValue ? 'translate-x-[1.15rem]' : 'translate-x-[3px]'"
    />
    <span v-if="label" class="ml-2 text-[13px] text-mid">{{ label }}</span>
  </button>
</template>

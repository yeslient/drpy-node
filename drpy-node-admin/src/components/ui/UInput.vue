<script setup>
defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  mono: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  size: { type: String, default: 'md' }, // sm / md
})
defineEmits(['update:modelValue', 'enter'])
</script>

<template>
  <div class="w-full">
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      spellcheck="false"
      class="w-full bg-sunken border rounded-ctrl text-hi placeholder-low transition-colors focus:outline-none disabled:opacity-50"
      :class="[
        size === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-[13px]',
        mono ? 'font-mono' : '',
        error ? 'border-danger/60 focus:ring-2 focus:ring-danger/40' : 'border-line focus:ring-2 focus:ring-accent/50 focus:border-accent/60',
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
      @keydown.enter="$emit('enter', $event)"
    >
    <p v-if="error" class="mt-1 text-xs text-danger">{{ error }}</p>
  </div>
</template>

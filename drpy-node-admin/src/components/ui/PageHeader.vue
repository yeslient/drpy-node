<script setup>
defineProps({
  eyebrow: { type: String, default: '' }, // 如 '// PLUGINS'
  title: { type: String, required: true },
  desc: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3 mb-4">
    <div class="min-w-0">
      <div v-if="eyebrow" class="eyebrow">{{ eyebrow }}</div>
      <h1 class="text-[15px] font-semibold tracking-wide text-hi mt-0.5">{{ title }}</h1>
      <p v-if="desc" class="text-xs text-mid mt-1">{{ desc }}</p>
    </div>
    <div v-if="$slots.actions" class="flex items-center gap-2 flex-wrap">
      <slot name="actions" />
    </div>
  </div>
</template>

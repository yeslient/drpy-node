<script setup>
import { computed } from 'vue'

const props = defineProps({
  tone: { type: String, default: 'neutral' }, // ok / warn / danger / info / accent / neutral
})

const toneClass = computed(() => ({
  ok: 'bg-ok/10 text-ok border-ok/25',
  warn: 'bg-warn/10 text-warn border-warn/25',
  danger: 'bg-danger/10 text-danger border-danger/25',
  info: 'bg-info/10 text-info border-info/25',
  accent: 'bg-accent-dim text-accent border-accent/30',
  neutral: 'bg-mid/10 text-mid border-line',
}[props.tone] || ''))
</script>

<template>
  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-2xs font-mono border" :class="toneClass">
    <slot />
  </span>
</template>

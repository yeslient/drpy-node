<script setup>
import { computed } from 'vue'

// 状态呼吸灯：running=青色脉冲 / stopped=灰 / error=红闪，贯穿全部列表
const props = defineProps({
  tone: { type: String, default: 'off' }, // running / ok / warn / error / off
  label: { type: String, default: '' },
})

const colorClass = computed(() => ({
  running: 'bg-accent',
  ok: 'bg-ok',
  warn: 'bg-warn',
  error: 'bg-danger',
  off: 'bg-low',
}[props.tone] || 'bg-low'))

const animClass = computed(() => ({
  running: 'animate-dot-breath',
  ok: '',
  warn: 'animate-dot-breath',
  error: 'animate-dot-error',
  off: '',
}[props.tone] || ''))
</script>

<template>
  <span class="inline-flex items-center gap-1.5" :title="label || tone">
    <span class="inline-block w-2 h-2 rounded-full shrink-0" :class="[colorClass, animClass]" />
    <span v-if="label" class="text-xs" :class="tone === 'off' ? 'text-low' : 'text-mid'">{{ label }}</span>
  </span>
</template>

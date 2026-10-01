<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  hint: { type: String, default: '' },
  tone: { type: String, default: 'neutral' }, // ok / warn / danger / accent / neutral
  dot: { type: String, default: '' }, // 传 StatusDot tone 则显示状态灯
})
</script>

<template>
  <div class="panel p-4">
    <div class="flex items-center justify-between gap-2">
      <span class="eyebrow">{{ label }}</span>
      <slot name="dot">
        <span v-if="dot" class="inline-block w-2 h-2 rounded-full animate-dot-breath" :class="{
          'bg-accent': dot === 'running',
          'bg-ok': dot === 'ok',
          'bg-warn': dot === 'warn',
          'bg-danger': dot === 'error',
          'bg-low': dot === 'off',
        }" />
      </slot>
    </div>
    <div
      class="mt-2 font-mono text-2xl leading-none tracking-tight"
      :class="{
        'text-hi': tone === 'neutral',
        'text-accent': tone === 'accent',
        'text-ok': tone === 'ok',
        'text-warn': tone === 'warn',
        'text-danger': tone === 'danger',
      }"
    >{{ value }}</div>
    <p v-if="hint" class="mt-1.5 text-2xs text-low">{{ hint }}</p>
  </div>
</template>

<script setup>
defineProps({
  rows: { type: Number, default: 3 }, // text 变体时的行数
  variant: { type: String, default: 'text' }, // text / panel / circle
})
</script>

<template>
  <div v-if="variant === 'text'" class="space-y-2.5" aria-hidden="true">
    <div
      v-for="i in rows"
      :key="i"
      class="h-4 rounded bg-mid/15 animate-pulse"
      :style="{ width: i === rows ? '62%' : '100%', animationDelay: `${i * 90}ms` }"
    />
  </div>
  <div v-else-if="variant === 'circle'" class="w-9 h-9 rounded-full bg-mid/15 animate-pulse" aria-hidden="true" />
  <div v-else class="panel p-4 space-y-3 animate-pulse" aria-hidden="true">
    <div class="h-4 w-1/3 rounded bg-mid/15" />
    <div class="h-3 w-2/3 rounded bg-mid/10" />
    <div class="h-3 w-1/2 rounded bg-mid/10" />
  </div>
</template>

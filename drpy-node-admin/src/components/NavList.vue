<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { navGroups, isActiveNav } from '../navigation'

defineProps({
  terminalAvailable: { type: Boolean, default: false },
})
defineEmits(['navigate'])
const route = useRoute()

// 当前激活项按「最长路径匹配」唯一确定：
// /plugins/market 同时命中 /plugins（前缀）与 /plugins/market（精确）时只亮后者
const activePath = computed(() => {
  const matches = navGroups
    .flatMap(g => g.items.map(i => i.path))
    .filter(p => isActiveNav(route.path, p))
    .sort((a, b) => b.length - a.length)
  return matches[0] || null
})
</script>

<template>
  <nav class="py-3">
    <div v-for="group in navGroups" :key="group.label" class="mb-4 last:mb-0">
      <div class="eyebrow px-4 mb-1.5">// {{ group.label }}</div>
      <RouterLink
        v-for="item in group.items"
        v-show="!item.needTerminal || terminalAvailable"
        :key="item.path"
        :to="item.path"
        class="relative flex items-center gap-2.5 mx-2 px-3.5 h-10 rounded-ctrl transition-colors"
        :class="activePath === item.path
          ? 'bg-accent-dim text-accent'
          : 'text-mid hover:text-hi hover:bg-mid/5'"
        @click="$emit('navigate')"
      >
        <span
          v-if="activePath === item.path"
          class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-4 bg-accent rounded-full"
        />
        <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
        </svg>
        <span class="text-[13px]">{{ item.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

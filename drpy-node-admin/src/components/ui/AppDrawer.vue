<script setup>
import { watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  side: { type: String, default: 'left' }, // left / right / bottom
  title: { type: String, default: '' },
})
const emit = defineEmits(['update:open'])

function close() { emit('update:open', false) }
function onKeydown(e) { if (e.key === 'Escape') close() }

watch(() => props.open, open => {
  if (open) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="fixed inset-0 z-[80]">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]" @click="close" />
        <div
          class="absolute bg-panel border-line flex flex-col"
          :class="[
            side === 'left' ? 'left-0 top-0 bottom-0 w-[280px] max-w-[85vw] border-r' :
            side === 'right' ? 'right-0 top-0 bottom-0 w-[320px] max-w-[88vw] border-l' :
            'left-0 right-0 bottom-0 max-h-[85vh] border-t rounded-t-panel',
          ]"
          :style="{ boxShadow: 'var(--shadow-float)' }"
          role="dialog"
          aria-modal="true"
        >
          <div v-if="title" class="flex items-center justify-between px-4 h-12 border-b border-line shrink-0">
            <h3 class="text-sm font-medium text-hi">{{ title }}</h3>
            <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" aria-label="关闭" @click="close">✕</button>
          </div>
          <div class="flex-1 overflow-y-auto pb-safe">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active > div:last-child,
.drawer-leave-active > div:last-child {
  transition: transform 0.2s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from > div:last-child,
.drawer-leave-to > div:last-child {
  transform: translateX(var(--drawer-x, -100%));
}
</style>

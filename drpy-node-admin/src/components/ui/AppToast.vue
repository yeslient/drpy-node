<script setup>
import { useToast } from '../../composables/useToast'

const { toasts, dismiss } = useToast()

const icons = {
  success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  error: 'M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.26 16A2 2 0 005.07 19z',
  warning: 'M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.26 16A2 2 0 005.07 19z',
  info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}
const tones = {
  success: 'text-ok',
  error: 'text-danger',
  warning: 'text-warn',
  info: 'text-info',
}
</script>

<template>
  <Teleport to="body">
    <!-- 移动端顶部全宽滑入 / PC 右上堆叠 -->
    <div class="fixed top-2 inset-x-2 sm:inset-x-auto sm:right-4 sm:top-4 z-[95] flex flex-col gap-2 sm:items-end pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          role="status"
          class="pointer-events-auto flex items-start gap-2.5 w-full sm:w-auto sm:max-w-sm bg-panel border border-line rounded-panel px-3.5 py-2.5"
          :style="{ boxShadow: 'var(--shadow-float)' }"
        >
          <svg class="w-4 h-4 mt-0.5 shrink-0" :class="tones[t.type]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[t.type] || icons.info" />
          </svg>
          <p class="flex-1 text-[13px] text-hi leading-snug break-words min-w-0">{{ t.message }}</p>
          <button
            class="ml-1 -mr-1 p-1 text-low hover:text-hi transition-colors shrink-0"
            aria-label="关闭提示"
            @click="dismiss(t.id)"
          >✕</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  animation: toastIn 0.2s ease both;
}
.toast-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
@keyframes toastIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

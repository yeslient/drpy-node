<script setup>
import { watch, nextTick, computed, ref } from 'vue'
import { useDialog } from '../../composables/useDialog'

const dialog = useDialog()
const { state, submit, close } = dialog

const requireInputRef = ref(null)

const panelClass = computed(() => [
  'fixed z-[96] bg-panel border border-line flex flex-col dialog-in',
  // 移动端：底部抽屉（bottom sheet）；≥sm：居中模态
  'inset-x-0 bottom-0 rounded-t-panel max-h-[88vh]',
  'sm:inset-x-auto sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:w-[26rem] sm:max-w-[calc(100vw-3rem)] sm:max-h-[80vh] sm:rounded-panel',
])

const confirmDisabled = computed(() => !!state.requireText && state.requireValue !== state.requireText)

function onKeydown(e) {
  if (e.key === 'Escape') close(false)
}
watch(() => state.open, async open => {
  if (open) {
    document.addEventListener('keydown', onKeydown)
    if (state.requireText || state.mode === 'form') {
      await nextTick()
      requireInputRef.value?.focus?.()
    }
  } else {
    document.removeEventListener('keydown', onKeydown)
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="dialog-fade">
      <div v-if="state.open" class="fixed inset-0 z-[94]">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]" @click="close(false)" />
        <div :class="panelClass" :style="{ boxShadow: 'var(--shadow-float)' }" role="dialog" aria-modal="true">
          <!-- 头部 -->
          <div class="px-5 pt-4 pb-3 border-b border-line shrink-0">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-[15px] font-semibold text-hi">{{ state.title }}</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" aria-label="关闭" @click="close(false)">✕</button>
            </div>
          </div>

          <!-- 内容 -->
          <div class="px-5 py-4 overflow-y-auto space-y-3">
            <p v-if="state.message" class="text-[13px] text-mid leading-relaxed break-words">{{ state.message }}</p>

            <!-- 高危确认词输入 -->
            <div v-if="state.requireText" class="space-y-1.5">
              <label class="text-xs text-mid">
                请输入 <span class="font-mono text-danger font-semibold">{{ state.requireText }}</span> 以确认执行
              </label>
              <input
                ref="requireInputRef"
                v-model="state.requireValue"
                type="text"
                class="input font-mono"
                :placeholder="state.requireText"
                autocomplete="off"
                spellcheck="false"
              >
            </div>

            <!-- 表单模式 -->
            <template v-if="state.mode === 'form' && state.fields">
              <div v-for="f in state.fields" :key="f.key" class="space-y-1.5">
                <label class="block text-xs text-mid">
                  {{ f.label }}<span v-if="f.required" class="text-danger"> *</span>
                </label>
                <select v-if="f.type === 'select'" v-model="state.values[f.key]" class="input">
                  <option v-for="opt in f.options" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </select>
                <textarea
                  v-else-if="f.type === 'textarea'"
                  v-model="state.values[f.key]"
                  class="input font-mono text-xs"
                  rows="4"
                  :placeholder="f.placeholder"
                  spellcheck="false"
                />
                <input
                  v-else
                  v-model="state.values[f.key]"
                  :type="f.type === 'password' ? 'password' : 'text'"
                  class="input"
                  :class="{ 'font-mono': f.mono }"
                  :placeholder="f.placeholder"
                  :autocomplete="f.autocomplete || 'off'"
                  spellcheck="false"
                >
              </div>
            </template>

            <p v-if="state.error" class="text-xs text-danger">{{ state.error }}</p>
          </div>

          <!-- 动作区：移动端贴底 -->
          <div class="px-5 py-3.5 pb-safe border-t border-line flex gap-2 justify-end shrink-0 sm:pb-3.5">
            <button class="btn btn-secondary flex-1 sm:flex-none min-h-[40px]" @click="close(false)">{{ state.cancelText }}</button>
            <button
              class="flex-1 sm:flex-none min-h-[40px] rounded-ctrl font-medium text-[13px] transition-all duration-150 active:translate-y-px disabled:opacity-40 disabled:pointer-events-none"
              :class="state.danger ? 'bg-danger text-white hover:brightness-110' : 'btn-primary'"
              :disabled="confirmDisabled"
              :title="confirmDisabled ? `请先输入确认词 ${state.requireText}` : ''"
              @click="submit"
            >{{ state.confirmText }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dialog-in {
  animation: dialogIn 0.15s ease both;
}
@keyframes dialogIn {
  from { opacity: 0; transform: translateY(10px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
@media (min-width: 640px) {
  .dialog-in {
    animation: dialogInDesktop 0.15s ease both;
  }
  @keyframes dialogInDesktop {
    from { opacity: 0; transform: translate(-50%, -50%) scale(0.96); }
    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
  }
}
.dialog-fade-enter-active,
.dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}
.dialog-fade-enter-from,
.dialog-fade-leave-to {
  opacity: 0;
}
</style>

import { reactive } from 'vue'

// 模块级单例状态：不依赖 provide/inject，KeepAlive 与任意组件内调用均安全
const toasts = reactive([])
let seq = 0

function dismiss(id) {
  const i = toasts.findIndex(t => t.id === id)
  if (i > -1) toasts.splice(i, 1)
}

function push(type, message, duration) {
  if (!message) return
  const t = { id: ++seq, type, message: String(message) }
  toasts.push(t)
  // 堆叠上限：最多同屏 5 条，最旧的先走
  if (toasts.length > 5) toasts.splice(0, toasts.length - 5)
  setTimeout(() => dismiss(t.id), duration)
}

export function useToast() {
  return {
    toasts,
    dismiss,
    success: (msg, duration = 4000) => push('success', msg, duration),
    error: (msg, duration = 5000) => push('error', msg, duration),
    warning: (msg, duration = 4500) => push('warning', msg, duration),
    info: (msg, duration = 4000) => push('info', msg, duration),
  }
}

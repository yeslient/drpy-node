import { reactive } from 'vue'

// Promise 化确认/表单弹窗的单例状态，替代原生 confirm/prompt
// confirm()  → Promise<boolean>
// form()     → Promise<values|null>
const state = reactive({
  open: false,
  mode: 'confirm', // 'confirm' | 'form'
  title: '',
  message: '',
  fields: null,
  values: {},
  danger: false,
  confirmText: '确认',
  cancelText: '取消',
  // 高危模式：要求输入确认词（如 RESTORE）后确认键才可用
  requireText: null,
  requireValue: '',
  error: '',
  resolve: null,
})

function close(result) {
  if (!state.open) return
  state.open = false
  const resolve = state.resolve
  state.resolve = null
  if (resolve) resolve(result)
}

function open(opts) {
  Object.assign(state, {
    open: true,
    message: '',
    fields: null,
    values: {},
    danger: false,
    confirmText: '确认',
    cancelText: '取消',
    requireText: null,
    requireValue: '',
    error: '',
    resolve: null,
  }, opts)
  return new Promise(resolve => { state.resolve = resolve })
}

export function useDialog() {
  return {
    state,
    close,
    confirm({ title, message = '', danger = false, confirmText = '确认', cancelText = '取消', requireText = null } = {}) {
      return open({ mode: 'confirm', title, message, danger, confirmText, cancelText, requireText })
    },
    form({ title, fields = [], values = {}, confirmText = '保存', cancelText = '取消' }) {
      return open({ mode: 'form', title, fields, values: { ...values }, confirmText, cancelText })
    },
    // 供 AppDialog 内部提交：校验 required 与确认词
    submit() {
      if (state.mode === 'form' && Array.isArray(state.fields)) {
        for (const f of state.fields) {
          const v = state.values[f.key]
          if (f.required && (v === undefined || v === null || String(v).trim() === '')) {
            state.error = `请填写「${f.label}」`
            return
          }
        }
      }
      if (state.requireText && state.requireValue !== state.requireText) return
      close(state.mode === 'form' ? { ...state.values } : true)
    },
  }
}

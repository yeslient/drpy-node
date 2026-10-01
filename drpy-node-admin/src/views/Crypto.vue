<script setup>
import { ref } from 'vue'
import apiClient from '../api/client'
import copy from 'clipboard-copy'
import { useToast } from '../composables/useToast'

const toast = useToast()
const inputCode = ref('')
const outputCode = ref('')
const cryptoType = ref('base64')
const authCode = ref('')
const loading = ref(false)
const error = ref('')

const cryptoOptions = [
  { value: 'base64', label: 'Base64' },
  { value: 'gzip', label: 'Gzip' },
  { value: 'aes', label: 'AES' },
  { value: 'rsa', label: 'RSA' },
  { value: 'drpy', label: 'Drpy源解密' }
]

const handleCopy = async () => {
  if (!outputCode.value) return
  try {
    await copy(outputCode.value)
    toast.success('已复制到剪贴板')
  } catch (e) {
    console.error('复制失败:', e)
    toast.error('复制失败')
  }
}

const handleEncode = async () => {
  if (!inputCode.value) {
    error.value = '请输入需要加密的文本'
    return
  }

  loading.value = true
  error.value = ''
  outputCode.value = ''

  try {
    const data = await apiClient.post('/encoder', {
      type: cryptoType.value,
      code: inputCode.value
    })

    if (data.error) {
      throw new Error(data.error)
    }
    outputCode.value = data.result
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const handleDecode = async () => {
  if (!inputCode.value) {
    error.value = '请输入需要解密的文本'
    return
  }

  loading.value = true
  error.value = ''
  outputCode.value = ''

  try {
    if (cryptoType.value === 'drpy') {
      if (!authCode.value) {
        error.value = 'Drpy源解密需要提供 Auth Code'
        loading.value = false
        return
      }

      const data = await apiClient.post('/decoder', {
        auth_code: authCode.value,
        code: inputCode.value
      })

      if (data.error) {
         throw new Error(data.error)
      }
      outputCode.value = data.result
    } else {
      // 其他类型解密
      const data = await apiClient.post('/api/admin/crypto/decode', {
        type: cryptoType.value,
        code: inputCode.value
      })

      if (data.error) {
         throw new Error(data.error)
      }
      outputCode.value = data.result
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}
const clearInput = () => {
  inputCode.value = ''
}

const clearOutput = () => {
  outputCode.value = ''
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// CRYPTO" title="加解密工具" desc="提供项目中常用的文本加解密和编码转换功能">
      <template #actions>
        <USelect v-model="cryptoType" :options="cryptoOptions" class="w-36" />
        <div v-if="cryptoType === 'drpy'" class="flex items-center gap-2">
          <input v-model="authCode" type="text" class="input h-10 w-40 sm:w-52 font-mono text-xs" placeholder="Auth Code" autocomplete="off" spellcheck="false">
        </div>
      </template>
    </PageHeader>

    <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-2 gap-3">
      <!-- 输入 -->
      <div class="panel flex flex-col overflow-hidden h-[360px] lg:h-auto" style="--i: 0">
        <div class="px-3 py-2 border-b border-line flex justify-between items-center gap-2 shrink-0 bg-sunken/50">
          <span class="eyebrow">// 输入文本</span>
          <div class="flex gap-1.5 items-center">
            <button v-if="inputCode" class="p-1.5 text-low hover:text-danger transition-colors" title="清空" @click="clearInput">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
            <UButton v-if="cryptoType !== 'drpy'" size="sm" variant="primary" :loading="loading" @click="handleEncode">加密</UButton>
            <UButton size="sm" :loading="loading" @click="handleDecode">解密</UButton>
          </div>
        </div>
        <textarea
          v-model="inputCode"
          class="flex-1 w-full p-3 bg-sunken border-0 font-mono text-xs text-hi resize-none focus:outline-none sunken-scroll"
          placeholder="在此输入待处理的文本…"
          spellcheck="false"
        />
      </div>

      <!-- 输出 -->
      <div class="panel flex flex-col overflow-hidden h-[360px] lg:h-auto relative" style="--i: 1">
        <div class="px-3 py-2 border-b border-line flex justify-between items-center shrink-0 bg-sunken/50">
          <span class="eyebrow">// 输出结果</span>
          <div class="flex gap-1.5 items-center">
            <button v-if="outputCode" class="p-1.5 text-low hover:text-danger transition-colors" title="清空" @click="clearOutput">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
            <UButton size="sm" variant="soft" :disabled="!outputCode" @click="handleCopy">复制</UButton>
          </div>
        </div>
        <div v-if="error" class="px-3 py-2 bg-danger/10 border-b border-danger/25 text-xs text-danger">{{ error }}</div>
        <textarea
          v-model="outputCode"
          class="flex-1 w-full p-3 bg-sunken/60 border-0 font-mono text-xs text-mid resize-none focus:outline-none sunken-scroll"
          readonly
          placeholder="处理结果将显示在这里…"
          spellcheck="false"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fileApi } from '../api/file'
import { spiderApi } from '../api/spider'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'
import { useThemeStore } from '../stores/theme'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()
const toast = useToast()
const dialog = useDialog()

const filePath = ref(route.query.path || '')
const fileContent = ref('')
const originalContent = ref('')
const loading = ref(false)
const saving = ref(false)
const validating = ref(false)
const validationError = ref(null)
const hasChanges = ref(false)
const wordWrap = ref('on') // Toggle for auto word wrap
let editorInstance = null // Reference to monaco editor instance

const handleEditorMount = (editor) => {
  editorInstance = editor
}

const toggleWordWrap = () => {
  wordWrap.value = wordWrap.value === 'on' ? 'off' : 'on'
  if (editorInstance) {
    editorInstance.updateOptions({ wordWrap: wordWrap.value })
  }
}

const editorLanguage = computed(() => {
  const path = filePath.value.toLowerCase()
  if (path.endsWith('.js')) return 'javascript'
  if (path.endsWith('.php')) return 'php'
  if (path.endsWith('.py')) return 'python'
  if (path.endsWith('.conf') || path.endsWith('.txt')) return 'plaintext'
  return 'javascript'
})

// Check if dark mode is active
const isDarkMode = computed(() => themeStore.isDark)


onMounted(async () => {
  if (filePath.value) {
    await loadFile()
  }
})

watch(() => route.query.path, async (newPath) => {
  if (newPath) {
    filePath.value = newPath
    await loadFile()
  }
})

watch(fileContent, () => {
  hasChanges.value = fileContent.value !== originalContent.value
})

const loadFile = async () => {
  loading.value = true
  try {
    const result = await fileApi.readFile(filePath.value)

    // 处理 MCP 返回格式 { type: 'text', content: "..." } 或 { type: 'image', dataUrl: "..." }
    let content = ''
    if (typeof result === 'string') {
      content = result
    } else if (result?.type === 'text' && result.content) {
      content = result.content
    } else if (result?.type === 'image' && result.dataUrl) {
      // 图片文件，在源编辑器中不处理图片
      content = '[图片文件，无法在源编辑器中显示]'
    } else if (result?.content) {
      content = result.content
    } else {
      content = String(result || '')
    }
    fileContent.value = content
    originalContent.value = content
  } catch (e) {
    console.error('Load file error:', e)
    toast.error('加载文件失败: ' + e.message)
    fileContent.value = ''
    originalContent.value = ''
  } finally {
    loading.value = false
  }
}

const saveFile = async () => {
  saving.value = true
  try {
    await fileApi.writeFile(filePath.value, fileContent.value)
    originalContent.value = fileContent.value
    hasChanges.value = false
    toast.success('保存成功')
  } catch (e) {
    toast.error('保存失败: ' + e.message)
  } finally {
    saving.value = false
  }
}

const validateFile = async () => {
  validating.value = true
  validationError.value = null
  try {
    const result = await spiderApi.checkSyntax(filePath.value)
    if (result.isError) {
      validationError.value = result.content?.[0]?.text || '语法错误'
      toast.error('语法检查未通过')
    } else {
      validationError.value = null
      toast.success('语法检查通过')
    }
  } catch (e) {
    validationError.value = e.message
    toast.error('语法检查失败: ' + e.message)
  } finally {
    validating.value = false
  }
}

const getTemplate = async () => {
  if (hasChanges.value) {
    const ok = await dialog.confirm({
      title: '载入模板',
      message: '当前内容有未保存的修改，载入模板将覆盖编辑区内容。确定继续吗？',
      danger: true,
      confirmText: '覆盖载入',
    })
    if (!ok) return
  }
  try {
    const result = await spiderApi.getTemplate()
    fileContent.value = result || ''
  } catch (e) {
    console.error('Get template error:', e)
    toast.error('获取模板失败: ' + e.message)
  }
}

const goBack = () => {
  router.back()
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// SOURCE EDITOR" title="源编辑器" :desc="filePath">
      <template #actions>
        <UButton size="sm" variant="ghost" @click="goBack">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          返回
        </UButton>
        <button
          class="inline-flex items-center gap-2 px-3 h-9 rounded-ctrl border text-xs transition-colors select-none cursor-pointer"
          :class="wordWrap === 'on' ? 'border-accent/40 bg-accent-dim text-accent' : 'border-line text-mid hover:text-hi'"
          @click="toggleWordWrap"
        >
          自动换行
        </button>
        <UButton size="sm" @click="getTemplate">获取模板</UButton>
        <UButton size="sm" :loading="validating" @click="validateFile">验证语法</UButton>
        <UButton size="sm" variant="primary" :loading="saving" :disabled="!hasChanges" @click="saveFile">保存</UButton>
      </template>
    </PageHeader>

    <div class="flex-1 flex flex-col min-h-0 gap-2">
      <!-- Validation error -->
      <div v-if="validationError" class="panel p-3.5 border-danger/40 bg-danger/5 shrink-0">
        <div class="flex items-start gap-2.5">
          <svg class="w-4 h-4 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="min-w-0">
            <h4 class="text-xs font-semibold text-danger">语法检查未通过</h4>
            <pre class="mt-1.5 text-xs text-mid whitespace-pre-wrap break-words max-h-32 overflow-y-auto">{{ validationError }}</pre>
          </div>
          <button class="ml-auto text-low hover:text-hi shrink-0" @click="validationError = null">✕</button>
        </div>
      </div>

      <!-- Monaco 编辑器 -->
      <div class="panel flex-1 overflow-hidden relative min-h-[320px]">
        <div v-if="loading" class="h-full flex items-center justify-center">
          <div class="w-7 h-7 rounded-full border-2 border-accent/30 border-t-accent animate-spin" />
        </div>
        <div v-else class="absolute inset-0">
          <vue-monaco-editor
            v-model:value="fileContent"
            :language="editorLanguage"
            :theme="isDarkMode ? 'vs-dark' : 'vs'"
            :options="{
              automaticLayout: true,
              minimap: { enabled: false },
              fontSize: 14,
              wordWrap: wordWrap,
              wordWrapColumn: 80,
              wrappingIndent: 'same',
              scrollBeyondLastLine: false,
              tabSize: 4
            }"
            height="100%"
            @mount="handleEditorMount"
          />
        </div>
      </div>

      <!-- 状态栏 -->
      <div class="shrink-0 flex items-center justify-between font-mono text-2xs text-low">
        <span>{{ fileContent.split('\n').length }} LINES · {{ editorLanguage.toUpperCase() }}</span>
        <span v-if="hasChanges" class="text-warn">● UNSAVED</span>
        <span v-else class="text-low">● SYNCED</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Fix mobile touch scrolling for Monaco editor */
:deep(.monaco-editor) {
  touch-action: pan-x pan-y !important;
}
:deep(.monaco-scrollable-element) {
  touch-action: pan-x pan-y !important;
}
</style>

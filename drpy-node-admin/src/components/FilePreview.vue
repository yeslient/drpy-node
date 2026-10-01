<script setup>
import { computed } from 'vue'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'
import { useThemeStore } from '../stores/theme'

const props = defineProps({
  type: { type: String, default: 'text' }, // 'text' | 'image' | 'unsupported'
  fileName: { type: String, default: '' },
  content: { type: String, default: '' },
  imageDataUrl: { type: String, default: '' },
  editable: { type: Boolean, default: false }, // true 时 monaco 进入编辑模式（input 事件回传内容）
})

const emit = defineEmits(['update:content'])

const themeStore = useThemeStore()

// 扩展名 → monaco 语言；未收录的一律 plaintext
const EXT_LANG = {
  js: 'javascript', mjs: 'javascript', cjs: 'javascript', jsx: 'javascript',
  ts: 'typescript', tsx: 'typescript',
  json: 'json',
  html: 'html', htm: 'html', vue: 'html',
  css: 'css', scss: 'scss', less: 'less',
  xml: 'xml',
  py: 'python', php: 'php', md: 'markdown',
  sh: 'shell', bash: 'shell', bat: 'bat', cmd: 'bat', ps1: 'powershell',
  sql: 'sql', yaml: 'yaml', yml: 'yaml', ini: 'ini', conf: 'ini', config: 'ini', env: 'ini',
  java: 'java', go: 'go', rs: 'rust',
  c: 'cpp', cpp: 'cpp', h: 'cpp', hpp: 'cpp', cs: 'csharp',
  rb: 'ruby', kt: 'kotlin', dart: 'dart', swift: 'swift', lua: 'lua', perl: 'perl',
}

const language = computed(() => {
  const name = props.fileName.toLowerCase()
  const ext = name.includes('.') ? name.split('.').pop() : ''
  return EXT_LANG[ext] || 'plaintext'
})

const editorOptions = computed(() => ({
  readOnly: !props.editable,
  automaticLayout: true,
  minimap: { enabled: false },
  fontSize: 12,
  wordWrap: 'on',
  scrollBeyondLastLine: false,
}))

const onEditorInput = (value) => {
  if (props.editable) emit('update:content', value ?? '')
}
</script>

<template>
  <div class="h-full min-h-0 flex flex-col">
    <div v-if="type === 'unsupported'" class="flex-1 flex items-center justify-center">
      <UEmpty icon="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 18.364m12.728-12.728L9 9m0 0l3 3m-3-3l3-3m-3 3h12" text="暂不支持预览此文件类型（支持文本与图片）" />
    </div>
    <div v-else-if="type === 'image'" class="flex-1 overflow-y-auto p-4 bg-sunken sunken-scroll flex items-start justify-center">
      <img :src="imageDataUrl" :alt="fileName" class="max-w-full h-auto">
    </div>
    <div v-else class="flex-1 min-h-0 bg-sunken sunken-scroll">
      <VueMonacoEditor
        :value="content"
        :language="language"
        :theme="themeStore.isDark ? 'vs-dark' : 'vs'"
        :options="editorOptions"
        height="100%"
        @update:value="onEditorInput"
      />
    </div>
  </div>
</template>

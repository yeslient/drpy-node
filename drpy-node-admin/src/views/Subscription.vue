<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../api/admin'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'
import { useThemeStore } from '../stores/theme'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const themeStore = useThemeStore()
const toast = useToast()
const dialog = useDialog()
const files = ref([])
const loading = ref(false)
const currentFile = ref(null)
const fileContent = ref('')
const originalContent = ref('')
const hasChanges = ref(false)
const saving = ref(false)

const loadFiles = async () => {
  loading.value = true
  try {
    const res = await adminApi.getSubFiles()
    // res is the payload directly
    if (res.success) {
        files.value = res.data
    } else {
        toast.error('加载文件列表失败: ' + (res.message || '未知错误'))
    }
  } catch (e) {
    toast.error('加载文件列表失败: ' + e.message)
  } finally {
    loading.value = false
  }
}

const selectFile = async (file) => {
    if (hasChanges.value) {
        const ok = await dialog.confirm({
          title: '未保存的修改',
          message: `当前文件 ${currentFile.value?.name} 有未保存的修改，切换后将丢失。确定要切换吗？`,
          danger: true,
          confirmText: '放弃并切换',
        })
        if (!ok) return
    }

    currentFile.value = file
    loading.value = true
    try {
        const res = await adminApi.getSubFile(file.name)
        if (res.success) {
            fileContent.value = res.data
            originalContent.value = res.data
            hasChanges.value = false
        } else {
            toast.error('加载文件内容失败: ' + (res.message || '未知错误'))
        }
    } catch (e) {
        toast.error('加载文件内容失败: ' + e.message)
    } finally {
        loading.value = false
    }
}

const saveFile = async () => {
    if (!currentFile.value) return
    saving.value = true
    try {
        const res = await adminApi.saveSubFile(currentFile.value.name, fileContent.value)
        if (res.success) {
            originalContent.value = fileContent.value
            hasChanges.value = false
            toast.success(`${currentFile.value.name} 已保存`)
        } else {
            toast.error('保存失败: ' + (res.message || '未知错误'))
        }
    } catch (e) {
        toast.error('保存出错: ' + e.message)
    } finally {
        saving.value = false
    }
}

const handleContentChange = (value) => {
    fileContent.value = value
    hasChanges.value = value !== originalContent.value
}

// Check if dark mode is active
const isDarkMode = computed(() => themeStore.isDark)

onMounted(() => {
    loadFiles()
})
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// SUBSCRIPTION" title="订阅管理" desc="编辑 public/sub 目录下的订阅文件 (sub.json) 和排序文件">
      <template #actions>
        <UBadge v-if="hasChanges" tone="warn">未保存</UBadge>
        <UButton size="sm" variant="primary" :loading="saving" :disabled="!currentFile || !hasChanges" @click="saveFile">保存</UButton>
        <UButton size="sm" :loading="loading" @click="loadFiles">刷新</UButton>
      </template>
    </PageHeader>

    <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-3 overflow-hidden">
      <!-- 文件列表：移动横滑 chips / PC 侧栏 -->
      <div class="panel shrink-0 flex lg:flex-col overflow-hidden lg:w-60">
        <div class="hidden lg:block px-4 py-2.5 border-b border-line shrink-0">
          <span class="eyebrow">// 文件列表</span>
        </div>
        <div class="flex lg:flex-col flex-1 overflow-x-auto lg:overflow-y-auto lg:overflow-x-hidden hide-scrollbar">
          <div v-if="loading && !currentFile" class="p-4 space-y-2 w-full">
            <USkeleton v-for="i in 4" :key="i" />
          </div>
          <template v-else>
            <button
              v-for="file in files" :key="file.name"
              class="px-4 py-3 text-left transition-colors whitespace-nowrap lg:whitespace-normal border-b lg:border-b border-line/60 lg:last:border-b-0 border-r lg:border-r-0 min-h-[44px] shrink-0 lg:shrink"
              :class="currentFile?.name === file.name ? 'bg-accent-dim text-accent' : 'text-mid hover:text-hi hover:bg-mid/5'"
              @click="selectFile(file)"
            >
              <div class="text-[13px] font-medium font-mono truncate">{{ file.name }}</div>
              <div class="hidden lg:block text-2xs text-low mt-0.5">{{ new Date(file.mtime).toLocaleString() }}</div>
            </button>
          </template>
        </div>
      </div>

      <!-- Monaco 编辑器 -->
      <div class="panel flex-1 flex flex-col overflow-hidden relative min-h-0">
        <div v-if="!currentFile" class="absolute inset-0 flex items-center justify-center">
          <UEmpty icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" text="请选择文件进行编辑" />
        </div>
        <div v-else-if="loading" class="absolute inset-0 flex items-center justify-center">
          <div class="w-6 h-6 rounded-full border-2 border-accent/30 border-t-accent animate-spin" />
        </div>
        <VueMonacoEditor
            v-else
            v-model:value="fileContent"
            :language="currentFile.name.endsWith('.json') ? 'json' : 'html'"
            :theme="isDarkMode ? 'vs-dark' : 'vs'"
            :options="{
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 14,
                wordWrap: 'on'
            }"
            @change="handleContentChange"
            class="h-[60vh] lg:h-full w-full"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>

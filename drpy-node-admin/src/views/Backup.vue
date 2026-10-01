<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '../api/admin'
import { fileApi } from '../api/file'
import { useDialog } from '../composables/useDialog'

const dialog = useDialog()
const config = ref({ paths: [] })
const loading = ref(false)
const processing = ref(false)
const logs = ref([])

// File picker state
const showFilePicker = ref(false)
const currentPath = ref('.')
const fileList = ref([])
const pathHistory = ref(['.'])

const addLog = (msg, type = 'info') => {
    logs.value.unshift({
        time: new Date().toLocaleTimeString(),
        msg,
        type
    })
}

const loadConfig = async () => {
    loading.value = true
    try {
        const res = await adminApi.getBackupConfig()
        if (res.success) {
            config.value = res
            if (!config.value.paths) config.value.paths = []
        } else {
            addLog('加载配置失败: ' + (res.message || '未知错误'), 'error')
        }
    } catch (e) {
        addLog('加载配置失败: ' + e.message, 'error')
    } finally {
        loading.value = false
    }
}

const saveConfig = async () => {
    try {
        const res = await adminApi.updateBackupConfig(config.value.paths)
        if (res.success) {
            addLog('配置保存成功', 'success')
        } else {
            addLog('配置保存失败: ' + res.message, 'error')
        }
    } catch (e) {
        addLog('配置保存出错: ' + e.message, 'error')
    }
}

const removePath = (index) => {
    config.value.paths.splice(index, 1)
    saveConfig()
}

const handleReset = async () => {
    const ok = await dialog.confirm({
        title: '重置包含文件',
        message: '将把备份包含文件列表重置为系统默认值，当前自定义列表会被覆盖。',
        danger: true,
        confirmText: '重置',
    })
    if (!ok) return
    processing.value = true
    addLog('正在重置包含文件...', 'info')
    try {
        const res = await adminApi.resetBackupConfig()
        if (res.success) {
            config.value.paths = res.paths
            addLog('重置包含文件成功', 'success')
        } else {
            addLog('重置失败: ' + res.message, 'error')
        }
    } catch (e) {
        addLog('重置出错: ' + e.message, 'error')
    } finally {
        processing.value = false
    }
}

const openFilePicker = async () => {
    currentPath.value = '.'
    pathHistory.value = ['.']
    await loadFileList(currentPath.value)
    showFilePicker.value = true
}

const closeFilePicker = () => {
    showFilePicker.value = false
}

const loadFileList = async (dirPath) => {
    try {
        const res = await fileApi.listDirectory(dirPath)
        if (res && res.files) {
            fileList.value = res.files.sort((a, b) => {
                if (a.isDirectory === b.isDirectory) return a.name.localeCompare(b.name)
                return a.isDirectory ? -1 : 1
            })
        }
    } catch (e) {
        addLog('加载文件列表失败: ' + e.message, 'error')
    }
}

const navigateTo = async (folder) => {
    const newPath = currentPath.value === '.' ? folder.name : `${currentPath.value}/${folder.name}`
    pathHistory.value.push(newPath)
    currentPath.value = newPath
    await loadFileList(newPath)
}

const navigateUp = async () => {
    if (pathHistory.value.length > 1) {
        pathHistory.value.pop()
        currentPath.value = pathHistory.value[pathHistory.value.length - 1]
        await loadFileList(currentPath.value)
    }
}

const selectPath = (item) => {
    let newPath = item.path
    if (newPath.startsWith('./')) newPath = newPath.substring(2)
    if (!config.value.paths.includes(newPath)) {
        config.value.paths.push(newPath)
        saveConfig()
    }
    closeFilePicker()
}

const handleBackup = async () => {
    const ok = await dialog.confirm({
        title: '立即备份',
        message: '将按包含文件列表打包当前系统配置，开始备份吗？',
        confirmText: '开始备份',
    })
    if (!ok) return
    processing.value = true
    addLog('开始备份...', 'info')
    try {
        const res = await adminApi.createBackup()
        if (res.success) {
            addLog('备份成功: ' + res.message, 'success')
            if (res.details) {
                res.details.forEach(d => addLog(d, 'info'))
            }
            await loadConfig() // Refresh timestamps
        } else {
            addLog('备份失败: ' + res.message, 'error')
        }
    } catch (e) {
        addLog('备份出错: ' + e.message, 'error')
    } finally {
        processing.value = false
    }
}

const handleRestore = async () => {
    // 高危操作：覆盖系统文件，强制输入确认词 RESTORE
    const ok = await dialog.confirm({
        title: '恢复备份',
        message: '恢复将使用备份包覆盖当前系统文件，未备份的自定义修改会丢失且不可恢复。',
        danger: true,
        confirmText: '确认恢复',
        requireText: 'RESTORE',
    })
    if (!ok) return
    processing.value = true
    addLog('开始恢复...', 'warning')
    try {
        const res = await adminApi.restoreBackup()
        if (res.success) {
            addLog('恢复成功: ' + res.message, 'success')
             if (res.details) {
                res.details.forEach(d => addLog(d, 'info'))
            }
            await loadConfig()
        } else {
            addLog('恢复失败: ' + res.message, 'error')
        }
    } catch (e) {
        addLog('恢复出错: ' + e.message, 'error')
    } finally {
        processing.value = false
    }
}

onMounted(() => {
    loadConfig()
    addLog('系统准备就绪')
})
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// BACKUP" title="备份恢复" desc="管理系统关键配置的备份与恢复">
      <template #actions>
        <div class="hidden md:flex flex-col text-2xs font-mono text-low border-l border-line pl-3 gap-0.5">
          <span>备份 {{ config.lastBackupAt ? new Date(config.lastBackupAt).toLocaleString() : '从未' }}</span>
          <span>恢复 {{ config.lastRestoreAt ? new Date(config.lastRestoreAt).toLocaleString() : '从未' }}</span>
        </div>
        <UButton size="sm" :disabled="processing" @click="handleBackup">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          立即备份
        </UButton>
        <UButton size="sm" :disabled="processing" @click="handleRestore">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m-4-4v12" /></svg>
          恢复备份
        </UButton>
        <UButton size="sm" variant="danger" :disabled="processing" @click="handleReset">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          重置包含
        </UButton>
      </template>
    </PageHeader>

    <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-3 enter-stagger">
      <!-- 包含文件 -->
      <div class="panel flex-1 flex flex-col min-h-0 overflow-hidden" style="--i: 0">
        <div class="px-4 py-2.5 border-b border-line flex justify-between items-center bg-sunken/50 shrink-0">
          <span class="eyebrow">// 包含文件</span>
          <button class="text-xs text-accent font-medium flex items-center gap-1" @click="openFilePicker">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            添加
          </button>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
          <div class="font-mono text-xs">
            <div v-for="(path, index) in config.paths" :key="path" class="py-2 px-3 flex justify-between items-center border-b border-dashed border-line/60 last:border-0 hover:bg-mid/[0.04] rounded-ctrl transition-colors">
              <span class="truncate break-all mr-2 text-mid" :title="path">{{ path }}</span>
              <button class="text-low hover:text-danger p-1.5 shrink-0 transition-colors" title="移除" @click="removePath(index)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </button>
            </div>
            <UEmpty v-if="!config.paths || config.paths.length === 0" icon="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" text="暂无包含文件" />
          </div>
        </div>
      </div>

      <!-- 操作日志 -->
      <div class="panel flex-1 flex flex-col min-h-0 overflow-hidden" style="--i: 1">
        <div class="px-4 py-2.5 border-b border-line flex justify-between items-center bg-sunken/50 shrink-0">
          <span class="eyebrow">// 操作日志</span>
          <span class="font-mono text-2xs text-low">{{ logs.length }} 条</span>
        </div>
        <div class="flex-1 overflow-y-auto p-3 bg-sunken font-mono text-xs sunken-scroll">
          <div v-for="(log, idx) in logs" :key="idx" class="mb-1.5 flex gap-2 pb-1 border-b border-line/50 last:border-0">
            <span class="text-low select-none shrink-0 w-[70px]">{{ log.time }}</span>
            <span class="break-words" :class="{
              'text-ok': log.type === 'success',
              'text-danger': log.type === 'error',
              'text-warn': log.type === 'warning',
              'text-mid': log.type === 'info'
            }">{{ log.msg }}</span>
          </div>
          <UEmpty v-if="logs.length === 0" icon="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" text="暂无日志记录" />
        </div>
      </div>
    </div>

    <!-- 文件选择器弹窗 -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showFilePicker" class="fixed inset-0 z-50 flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="closeFilePicker">
          <div class="bg-panel border border-line w-full sm:max-w-lg flex flex-col max-h-[88dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">选择文件或目录</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="closeFilePicker">✕</button>
            </div>

            <div class="p-4 space-y-2.5 overflow-y-auto">
              <div class="text-xs font-mono text-mid bg-sunken border border-line p-2 rounded-ctrl flex items-center gap-2">
                <button v-if="pathHistory.length > 1" class="p-1 text-accent hover:brightness-125" title="上一级" @click="navigateUp">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </button>
                {{ currentPath }}
              </div>

              <div class="border border-line rounded-ctrl overflow-hidden h-64 overflow-y-auto">
                <div v-for="item in fileList" :key="item.name" class="hover:bg-mid/[0.04] transition-colors border-b border-line/50 last:border-0">
                  <div class="flex items-center px-3 py-2.5 cursor-pointer min-h-[44px]" @click="item.isDirectory ? navigateTo(item) : selectPath(item)">
                    <svg v-if="item.isDirectory" class="w-5 h-5 text-warn mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-low mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span class="text-[13px] text-hi flex-1 truncate">{{ item.name }}</span>
                    <button v-if="item.isDirectory" class="ml-2 px-2 py-1 text-2xs bg-accent-dim text-accent border border-accent/30 rounded-ctrl hover:border-accent/60 transition-colors" @click.stop="selectPath(item)">
                      选择此目录
                    </button>
                  </div>
                </div>
                <div v-if="fileList.length === 0" class="px-4 py-6 text-center text-xs text-low">空目录</div>
              </div>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end shrink-0 sm:pb-3">
              <UButton size="sm" @click="closeFilePicker">取消</UButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.15s ease;
}
.modal-enter-active > div,
.modal-leave-active > div {
  transition: transform 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-from > div,
.modal-leave-to > div {
  transform: translateY(24px);
}
@media (min-width: 640px) {
  .modal-enter-from > div,
  .modal-leave-to > div {
    transform: scale(0.96);
  }
}
</style>

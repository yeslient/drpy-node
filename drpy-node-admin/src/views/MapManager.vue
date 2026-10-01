<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { fileApi } from '../api/file'
import Sortable from 'sortablejs'
import { useThemeStore } from '../stores/theme'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const themeStore = useThemeStore()
const toast = useToast()
const dialog = useDialog()
const loading = ref(false)
const saving = ref(false)
const viewMode = ref('list') // 'list' or 'editor'
const mapContent = ref('')
const originalMapContent = ref('')
const mapRecords = ref([])
const sortableInstance = ref(null)
const listContainer = ref(null)
const wordWrap = ref(true)
const searchQuery = ref('')
const editError = ref('')

const hasUnsavedChanges = computed(() => {
  if (viewMode.value === 'list') {
    return generateMapContent(mapRecords.value) !== originalMapContent.value
  } else {
    return mapContent.value !== originalMapContent.value
  }
})

const editorOptions = computed(() => ({
  automaticLayout: true,
  wordWrap: wordWrap.value ? 'on' : 'off',
  wordWrapColumn: 80,
  wrappingIndent: 'same',
  minimap: { enabled: false },
  scrollBeyondLastLine: false,
  fontSize: 14,
  lineNumbers: 'on',
  renderWhitespace: 'none',
  padding: { top: 16, bottom: 16 }
}))

// For list view editing
const editingIndex = ref(-1)
const editRecord = ref({ source: '', params: '', alias: '' })
const isAdding = ref(false)

let nextId = 0
const parseMapContent = (content) => {
  if (!content) return []
  const lines = content.split('\n')
  const records = []

  for (const line of lines) {
    const trimmedLine = line.trim()
    if (!trimmedLine) continue

    // Ignore comments if any, though map.txt doesn't officially support comments
    if (trimmedLine.startsWith('//') || trimmedLine.startsWith('#')) {
      records.push({ id: nextId++, type: 'comment', raw: line })
      continue
    }

    const parts = trimmedLine.split('@@')
    if (parts.length >= 1) {
      records.push({
        id: nextId++,
        type: 'record',
        raw: line,
        source: parts[0]?.trim() || '',
        params: parts[1]?.trim() || '',
        alias: parts[2]?.trim() || ''
      })
    } else {
      records.push({ id: nextId++, type: 'raw', raw: line })
    }
  }

  return records
}

const generateMapContent = (records) => {
  return records.map(r => {
    if (r.type === 'record') {
      const parts = [r.source]
      if (r.params || r.alias) parts.push(r.params)
      if (r.alias) parts.push(r.alias)
      return parts.join('@@')
    }
    return r.raw
  }).join('\n')
}

const loadMapFile = async () => {
  loading.value = true
  try {
    const result = await fileApi.readFile('config/map.txt')

    let content = ''
    if (typeof result === 'string') {
      content = result
    } else if (result?.type === 'text' && result.content) {
      content = result.content
    } else if (result?.content) {
      content = result.content
    } else {
      content = String(result || '')
    }

    // parse→generate 不是恒等变换（去行尾空白/\r、丢末尾换行），而列表视图和
    // 编辑器视图的比对内容都来自还原结果，所以以规范化后的内容作为「未保存」基准，
    // 否则原文只要带尾随换行一进页面就会误报未保存
    const normalized = generateMapContent(parseMapContent(content))
    mapContent.value = normalized
    originalMapContent.value = normalized
    mapRecords.value = parseMapContent(content)
  } catch (e) {
    console.error('Load map.txt error:', e)
    // If file doesn't exist, we start with empty
    if (e.message && e.message.includes('not found')) {
      mapContent.value = ''
      originalMapContent.value = ''
      mapRecords.value = []
    } else {
      toast.error('加载 map.txt 失败: ' + e.message)
    }
  } finally {
    loading.value = false
  }
}

// 放弃未保存的修改，恢复到加载/上次保存时的内容（防止误点保存配置写入非预期内容）
const discardChanges = async () => {
  const ok = await dialog.confirm({
    title: '撤销修改',
    message: '将放弃当前所有未保存的修改，恢复到上次保存的内容。确定撤销吗？',
    danger: true,
    confirmText: '撤销修改',
  })
  if (!ok) return
  mapContent.value = originalMapContent.value
  mapRecords.value = parseMapContent(originalMapContent.value)
  cancelEdit()
  toast.info('已撤销未保存的修改')
}

const saveMapFile = async () => {
  const ok = await dialog.confirm({
    title: '保存映射配置',
    message: '此操作将直接写入 config/map.txt 文件。确定保存吗？',
    confirmText: '保存写入',
  })
  if (!ok) return

  saving.value = true
  try {
    // If in list mode, update content from records first
    if (viewMode.value === 'list') {
      mapContent.value = generateMapContent(mapRecords.value)
    }

    await fileApi.writeFile('config/map.txt', mapContent.value)
    // Update original content state after successful save
    originalMapContent.value = mapContent.value
    // Refresh records after save if we were in editor mode
    if (viewMode.value === 'editor') {
      mapRecords.value = parseMapContent(mapContent.value)
    }
    toast.success('映射配置已保存')
  } catch (e) {
    toast.error('保存失败: ' + e.message)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadMapFile()
  await nextTick()
  if (viewMode.value === 'list') {
    initSortable()
  }
})

const toggleViewMode = () => {
  if (viewMode.value === 'list') {
    // Switching to editor, sync content
    mapContent.value = generateMapContent(mapRecords.value)
    viewMode.value = 'editor'
    if (sortableInstance.value) {
      sortableInstance.value.destroy()
      sortableInstance.value = null
    }
  } else {
    // Switching to list, sync records
    mapRecords.value = parseMapContent(mapContent.value)
    viewMode.value = 'list'
    // Re-initialize Sortable after switching to list mode
    nextTick(() => {
      if (!sortableInstance.value) {
        initSortable()
      }
    })
  }
}

const initSortable = () => {
  const el = document.getElementById('map-records-list') || listContainer.value
  if (el && !sortableInstance.value && viewMode.value === 'list') {
    sortableInstance.value = new Sortable(el, {
      animation: 150,
      handle: '.drag-handle',
      ghostClass: 'opacity-40',
      onEnd: (evt) => {
        // Prevent sorting when searching
        if (searchQuery.value) return

        // To make Sortable and Vue play nicely together with DOM updates,
        // we first revert Sortable's DOM manipulation.
        const itemEl = evt.item
        if (evt.newIndex !== evt.oldIndex) {
          // Move the element back to its original position in the DOM
          if (evt.oldIndex > evt.newIndex) {
            itemEl.parentNode.insertBefore(itemEl, itemEl.parentNode.children[evt.oldIndex + 1] || null)
          } else {
            itemEl.parentNode.insertBefore(itemEl, itemEl.parentNode.children[evt.oldIndex] || null)
          }
        }

        // Then let Vue handle the state and reactivity update
        const item = mapRecords.value.splice(evt.oldIndex, 1)[0]
        mapRecords.value.splice(evt.newIndex, 0, item)
        // Force reactivity update
        mapRecords.value = [...mapRecords.value]
      }
    })
  }
}

// Watch for DOM updates to initialize Sortable
watch(viewMode, async (newMode) => {
  if (newMode === 'list') {
    await nextTick()
    if (!sortableInstance.value) {
      initSortable()
    }
  }
})

watch(searchQuery, (newVal) => {
  if (sortableInstance.value) {
    sortableInstance.value.option('disabled', !!newVal)
  }
})

const filteredRecords = computed(() => {
  if (!searchQuery.value) return mapRecords.value
  const query = searchQuery.value.toLowerCase()
  return mapRecords.value.filter(record => {
    if (record.type !== 'record') return false
    return (record.source && record.source.toLowerCase().includes(query)) ||
           (record.alias && record.alias.toLowerCase().includes(query))
  })
})

const getOriginalIndex = (record) => {
  return mapRecords.value.indexOf(record)
}

const startAdd = () => {
  isAdding.value = true
  editingIndex.value = -1
  editError.value = ''
  editRecord.value = { id: null, source: '', params: '', alias: '' }
}

const startEdit = (index) => {
  isAdding.value = false
  editingIndex.value = index
  editError.value = ''
  const record = mapRecords.value[index]
  editRecord.value = { ...record }
}

const cancelEdit = () => {
  isAdding.value = false
  editingIndex.value = -1
  editError.value = ''
}

const saveEdit = () => {
  if (!editRecord.value.source.trim()) {
    editError.value = '站名不能为空'
    return
  }

  const newRecord = {
    id: isAdding.value ? nextId++ : editRecord.value.id,
    type: 'record',
    source: editRecord.value.source.trim(),
    params: editRecord.value.params.trim(),
    alias: editRecord.value.alias.trim()
  }

  if (isAdding.value) {
    mapRecords.value.push(newRecord)
  } else if (editingIndex.value >= 0) {
    mapRecords.value[editingIndex.value] = newRecord
  }

  cancelEdit()
}

const deleteRecord = async (index) => {
  const ok = await dialog.confirm({
    title: '删除映射记录',
    message: `确定删除「${mapRecords.value[index]?.source}」这条映射吗？`,
    danger: true,
    confirmText: '删除',
  })
  if (!ok) return
  mapRecords.value.splice(index, 1)
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// MAP MANAGER" title="站源映射" desc="管理 config/map.txt 别名映射配置">
      <template #actions>
        <UBadge v-if="hasUnsavedChanges" tone="warn">未保存</UBadge>
        <UButton v-if="hasUnsavedChanges" size="sm" @click="discardChanges">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v1m0-6l-3-3m3 3l-3 3" /></svg>
          撤销修改
        </UButton>
        <label v-if="viewMode === 'editor'" class="hidden sm:flex items-center gap-2 text-xs text-mid cursor-pointer select-none">
          <USwitch v-model="wordWrap" />
          自动换行
        </label>
        <UButton size="sm" @click="toggleViewMode">
          <svg v-if="viewMode === 'list'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
          <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
          {{ viewMode === 'list' ? '文本编辑' : '列表模式' }}
        </UButton>
        <UButton size="sm" variant="primary" :loading="saving" @click="saveMapFile">
          <svg v-if="!saving" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
          保存配置
        </UButton>
      </template>
    </PageHeader>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="w-7 h-7 rounded-full border-2 border-accent/30 border-t-accent animate-spin" />
    </div>

    <!-- Editor Mode -->
    <div v-else-if="viewMode === 'editor'" class="flex-1 panel overflow-hidden flex flex-col min-h-0">
      <vue-monaco-editor
        v-model:value="mapContent"
        language="plaintext"
        :theme="themeStore.isDark ? 'vs-dark' : 'vs'"
        :options="editorOptions"
        class="flex-1 w-full"
      />
    </div>

    <!-- List Mode -->
    <div v-else class="flex-1 flex flex-col min-h-0 gap-3">
      <!-- 工具行 -->
      <div class="flex flex-wrap gap-2 items-center shrink-0">
        <UButton size="sm" variant="primary" :disabled="isAdding || editingIndex >= 0" @click="startAdd">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          添加映射
        </UButton>
        <div class="flex-1" />
        <div class="relative w-full sm:w-56">
          <input v-model="searchQuery" type="text" class="input h-9 pl-9 pr-8" placeholder="搜索站名或别名…">
          <svg class="w-3.5 h-3.5 text-low absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
        </div>
      </div>

      <!-- 编辑表单 -->
      <div v-if="isAdding || editingIndex >= 0" class="panel p-4 border-accent/40 shrink-0">
        <div class="eyebrow mb-3">{{ isAdding ? '// 新增映射记录' : '// 编辑映射记录' }}</div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-3">
          <div class="md:col-span-3">
            <label class="block text-xs text-mid mb-1">站名 <span class="text-danger">*</span></label>
            <input v-model="editRecord.source" type="text" class="input h-9 font-mono text-xs" placeholder="例如: webdav[盘]">
          </div>
          <div class="md:col-span-3">
            <label class="block text-xs text-mid mb-1">别名</label>
            <input v-model="editRecord.alias" type="text" class="input h-9 font-mono text-xs" placeholder="例如: WebDav[盘]">
          </div>
          <div class="md:col-span-6">
            <label class="block text-xs text-mid mb-1">参数</label>
            <textarea v-model="editRecord.params" class="input resize-y font-mono text-xs" rows="2" placeholder="例如: ?render=1&type=url&params=…" />
          </div>
        </div>
        <p v-if="editError" class="text-xs text-danger mb-2">{{ editError }}</p>
        <div class="flex justify-end gap-2">
          <UButton size="sm" @click="cancelEdit">取消</UButton>
          <UButton size="sm" variant="primary" @click="saveEdit">确定</UButton>
        </div>
      </div>

      <!-- 记录列表 -->
      <div class="panel overflow-hidden flex-1 flex flex-col relative min-h-0">
        <!-- ≥md：网格行 -->
        <div class="hidden md:flex flex-col flex-1 min-h-0">
          <div class="sticky top-0 z-10 bg-sunken px-4 py-2.5 border-b border-line grid grid-cols-12 gap-2 eyebrow">
            <div class="col-span-1 text-center">排序</div>
            <div class="col-span-2">站名</div>
            <div class="col-span-2">别名</div>
            <div class="col-span-6">参数</div>
            <div class="col-span-1 text-right">操作</div>
          </div>

          <div class="overflow-auto flex-1 p-2" ref="listContainer" id="map-records-list">
            <template v-for="record in filteredRecords" :key="record.id">
              <!-- 映射记录行 -->
              <div v-if="record.type === 'record'" class="group flex items-center px-2 py-1.5 hover:bg-mid/[0.04] rounded-ctrl mb-1 border border-transparent hover:border-line bg-panel transition-colors" :data-id="record.id">
                <div class="grid grid-cols-12 gap-2 w-full items-center">
                  <div class="col-span-1 flex justify-center">
                    <button class="drag-handle p-1.5 text-low hover:text-hi cursor-move disabled:opacity-40" :disabled="!!searchQuery" title="拖拽排序">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                    </button>
                  </div>
                  <div class="col-span-2 font-medium text-[13px] text-hi truncate" :title="record.source">{{ record.source }}</div>
                  <div class="col-span-2 text-[13px] text-accent truncate" :title="record.alias">{{ record.alias || '—' }}</div>
                  <div class="col-span-6 font-mono text-2xs text-mid truncate" :title="record.params">{{ record.params || '—' }}</div>
                  <div class="col-span-1 flex justify-end gap-0.5">
                    <button class="p-1.5 text-info hover:bg-info/10 rounded-ctrl transition-colors" title="编辑" @click="startEdit(getOriginalIndex(record))">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </button>
                    <button class="p-1.5 text-danger hover:bg-danger/10 rounded-ctrl transition-colors" title="删除" @click="deleteRecord(getOriginalIndex(record))">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </div>
              </div>
              <!-- 注释/原始行 -->
              <div v-else class="px-4 py-1.5 text-2xs text-low italic font-mono bg-sunken/60 rounded-ctrl mb-1">{{ record.raw }}</div>
            </template>
          </div>
        </div>

        <!-- <md：卡片堆叠 -->
        <div class="md:hidden overflow-y-auto flex-1 p-2.5 space-y-2">
          <template v-for="record in filteredRecords" :key="record.id">
            <div v-if="record.type === 'record'" class="border border-line rounded-ctrl p-3 bg-panel">
              <div class="flex items-center justify-between gap-2 mb-1.5">
                <span class="text-[13px] font-medium text-hi truncate">{{ record.source }}</span>
                <div class="flex gap-0.5 shrink-0">
                  <button class="p-2 text-info" title="编辑" @click="startEdit(getOriginalIndex(record))">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                  </button>
                  <button class="p-2 text-danger" title="删除" @click="deleteRecord(getOriginalIndex(record))">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </div>
              </div>
              <div v-if="record.alias" class="text-xs text-accent mb-1">{{ record.alias }}</div>
              <div v-if="record.params" class="font-mono text-2xs text-low break-all">{{ record.params }}</div>
            </div>
            <div v-else class="px-3 py-1.5 text-2xs text-low italic font-mono bg-sunken/60 rounded-ctrl">{{ record.raw }}</div>
          </template>
        </div>

        <UEmpty v-if="filteredRecords.length === 0" :text="searchQuery ? '没有匹配的映射记录' : '暂无映射记录，点击「添加映射」开始'" />
      </div>
    </div>
  </div>
</template>

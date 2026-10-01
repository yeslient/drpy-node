<script setup>
import { ref, computed, onMounted } from 'vue'
import { fileApi } from '../api/file'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'
import FilePreview from '../components/FilePreview.vue'

const toast = useToast()
const dialog = useDialog()
const currentPath = ref('.')
const files = ref([])
const loading = ref(false)
const selectedFile = ref(null)
const fileContent = ref('')
const fileType = ref('text') // 'text', 'image', or 'unsupported'
const imageDataUrl = ref('')

// 编辑模式（editable 文件：根目录 json / config js-json / json 下用户自建 json）
const editing = ref(false)
const editContent = ref('')
const editSaving = ref(false)

// 新建文件（仅根目录 json、config 与 json 目录开放）
const showCreate = ref(false)
const newName = ref('')
const newContent = ref('')
const createBusy = ref(false)

// 支持的文件类型
const SUPPORTED_TEXT_EXTENSIONS = new Set([
  'js', 'json', 'md', 'txt', 'html', 'htm', 'css', 'scss', 'less', 'xml', 'py', 'php',
  'ts', 'vue', 'jsx', 'tsx', 'yaml', 'yml', 'ini', 'conf', 'config', 'env',
  'gitignore', 'eslintrc', 'prettierrc', 'babelrc', 'editorconfig',
  'lock', 'csv', 'sql', 'sh', 'bat', 'cmd', 'ps1', 'log', 'out', 'err',
  'c', 'cpp', 'h', 'hpp', 'cs', 'go', 'rs', 'rb', 'java', 'kt', 'dart', 'swift',
  'properties', 'toml', 'pom', 'gradle'
])

const SUPPORTED_IMAGE_EXTENSIONS = new Set([
  'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'bmp', 'tiff', 'tif'
])

const EXACT_TEXT_FILES = new Set([
  'license', 'makefile', 'dockerfile', 'caddyfile', 'readme', 'changelog', 'authors'
])

const getFileType = (fileName) => {
  const nameLower = fileName.toLowerCase()
  let ext = ''
  if (nameLower.includes('.')) {
    ext = nameLower.split('.').pop()
  }

  if (ext && SUPPORTED_IMAGE_EXTENSIONS.has(ext)) return 'image'
  if ((ext && SUPPORTED_TEXT_EXTENSIONS.has(ext)) || EXACT_TEXT_FILES.has(nameLower)) return 'text'
  return 'unsupported'
}

const isSupportedFile = (fileName) => {
  return getFileType(fileName) !== 'unsupported'
}

const pathParts = computed(() => {
  const parts = currentPath.value.split('/').filter(Boolean)
  return [{ name: '根目录', path: '.' }, ...parts.map((part, i) => ({
    name: part,
    path: parts.slice(0, i + 1).join('/')
  }))]
})

const isSafePath = (path) => {
  // Prevent directory traversal
  return !path.includes('..') && !path.startsWith('~')
}

onMounted(() => {
  loadDirectory('.')
})

const loadDirectory = async (path) => {
  if (!isSafePath(path)) {
    toast.warning('不安全的路径')
    return
  }

  loading.value = true
  currentPath.value = path
  selectedFile.value = null
  fileContent.value = ''
  fileType.value = 'text'
  imageDataUrl.value = ''
  editing.value = false

  try {
    const result = await fileApi.listDirectory(path)
    files.value = result.files || []
  } catch (e) {
    toast.error('加载目录失败: ' + e.message)
  } finally {
    loading.value = false
  }
}

const openFile = async (file) => {
  if (!isSafePath(file.path)) {
    toast.warning('不安全的路径')
    return
  }
  editing.value = false

  // 检查文件类型
  const type = getFileType(file.name)
  if (type === 'unsupported') {
    selectedFile.value = file
    fileType.value = 'unsupported'
    fileContent.value = ''
    imageDataUrl.value = ''
    return
  }

  loading.value = true
  try {
    const result = await fileApi.readFile(file.path)
    selectedFile.value = file
    fileType.value = type

    // 处理新的返回格式：{ type: 'text', content: '...' } 或 { type: 'image', dataUrl: '...' }
    if (type === 'image') {
      // Image file
      if (typeof result === 'string') {
        imageDataUrl.value = result
      } else if (result?.type === 'image' && result.dataUrl) {
        imageDataUrl.value = result.dataUrl
      } else {
        imageDataUrl.value = result || ''
      }
      fileContent.value = ''
    } else {
      // Text file
      if (typeof result === 'string') {
        fileContent.value = result
      } else if (result?.type === 'text' && result.content) {
        fileContent.value = result.content
      } else {
        fileContent.value = String(result || '')
      }
      imageDataUrl.value = ''
    }
  } catch (e) {
    console.error('Read file error:', e)
    toast.error('读取文件失败: ' + e.message)
    selectedFile.value = null
  } finally {
    loading.value = false
  }
}

const closeFile = () => {
  selectedFile.value = null
  fileContent.value = ''
  fileType.value = 'text'
  imageDataUrl.value = ''
  editing.value = false
}

// ==================== 新建 / 编辑 / 删除 ====================

// 可新建目录：根目录（.json）、config（.js/.json）、json 及其子目录（.json）
const createAllowed = computed(() => {
  const p = currentPath.value
  return p === 'config' || p === 'json' || p.startsWith('config/') || p.startsWith('json/')
})
// 初始内容按扩展名给模板（json {} / js 注释头 / txt、m3u 留空）
const templateFor = (name) => {
  const ext = (name.split('.').pop() || '').toLowerCase()
  if (ext === 'json') return '{}'
  if (ext === 'js') return '// '
  return ''
}

const openCreate = () => {
  newName.value = ''
  newContent.value = ''
  showCreate.value = true
}

const createFile = async () => {
  const name = newName.value.trim()
  if (!name) {
    toast.warning('请输入文件名')
    return
  }
  if (newContent.value === '') newContent.value = templateFor(name)
  const path = currentPath.value === '.' ? name : `${currentPath.value}/${name}`
  createBusy.value = true
  try {
    await fileApi.writeFile(path, newContent.value)
    toast.success('文件已创建')
    showCreate.value = false
    await loadDirectory(currentPath.value)
    await openFile({ name, path, isDirectory: false })
  } catch (e) {
    toast.error(e?.response?.data?.error || '创建失败: ' + e.message)
  } finally {
    createBusy.value = false
  }
}

const canEdit = (file) => !!file && file.editable
const startEdit = () => {
  editing.value = true
  editContent.value = fileContent.value
}
const cancelEdit = () => {
  editing.value = false
  editContent.value = ''
}
const saveEdit = async () => {
  if (!selectedFile.value) return
  editSaving.value = true
  try {
    await fileApi.writeFile(selectedFile.value.path, editContent.value)
    fileContent.value = editContent.value
    editing.value = false
    toast.success('保存成功')
  } catch (e) {
    toast.error(e?.response?.data?.error || '保存失败: ' + e.message)
  } finally {
    editSaving.value = false
  }
}

const deleteFileEntry = async (file) => {
  const ok = await dialog.confirm({
    title: `删除 ${file.name}`,
    message: `将删除文件 ${file.path}，操作不可恢复。`,
    danger: true,
    confirmText: '删除',
  })
  if (!ok) return
  try {
    await fileApi.deleteFile(file.path)
    toast.success(`已删除 ${file.name}`)
    if (selectedFile.value?.path === file.path) closeFile()
    await loadDirectory(currentPath.value)
  } catch (e) {
    toast.error(e?.response?.data?.error || '删除失败: ' + e.message)
  }
}

// 移动端抽屉只在 <lg 视口弹出；PC 上预览直接显示在右栏（与 hidden lg:flex 断点一致）
const isMobileViewport = () => !window.matchMedia('(min-width: 1024px)').matches

const getFileIcon = (file) => {
  if (file.isDirectory) {
    return '📁'
  }
  const ext = file.name.split('.').pop()?.toLowerCase()
  const icons = {
    js: '📜',
    json: '📋',
    md: '📝',
    txt: '📄',
    html: '🌐',
    css: '🎨',
    png: '🖼️',
    jpg: '🖼️',
    svg: '🎭',
    gif: '🎞️'
  }
  return icons[ext] || '📄'
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// FILES" title="文件管理" desc="浏览和编辑项目文件" />

    <!-- 面包屑 -->
    <div class="panel px-3 py-2 mb-3 shrink-0">
      <nav class="flex items-center gap-0.5 text-xs overflow-x-auto hide-scrollbar" aria-label="面包屑">
        <template v-for="(part, index) in pathParts" :key="part.path">
          <button
            class="px-2 py-1 rounded-ctrl font-mono whitespace-nowrap transition-colors min-h-[32px]"
            :class="index === pathParts.length - 1 ? 'text-accent font-medium' : 'text-mid hover:text-hi hover:bg-mid/5'"
            @click="loadDirectory(part.path)"
          >{{ part.name }}</button>
          <svg v-if="index < pathParts.length - 1" class="w-3 h-3 text-low shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </template>
      </nav>
    </div>

    <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-2 gap-3">
      <!-- 文件列表 -->
      <div class="panel flex flex-col min-h-0 overflow-hidden">
        <div class="px-4 py-2.5 border-b border-line flex items-center justify-between shrink-0 gap-2">
          <span class="eyebrow">// 目录内容</span>
          <div class="flex items-center gap-2">
            <span class="font-mono text-2xs text-low">{{ files.length }} 项</span>
            <UButton
              size="sm"
              :variant="createAllowed ? 'primary' : 'soft'"
              :title="createAllowed ? '新建文件' : '仅根目录 json、config 与 json 目录支持新建'"
              :disabled="!createAllowed"
              @click="openCreate"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              新建
            </UButton>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto divide-y divide-line/60">
          <div v-if="loading" class="p-4 space-y-2">
            <USkeleton v-for="i in 6" :key="i" />
          </div>

          <div
            v-for="file in files"
            :key="file.name"
            class="w-full text-left px-4 py-3 hover:bg-mid/[0.04] active:bg-accent-dim transition-colors flex items-center gap-3 min-h-[44px] cursor-pointer"
            :class="{ 'opacity-50': !file.isDirectory && !isSupportedFile(file.name) }"
            @click="file.isDirectory ? loadDirectory(file.path) : openFile(file)"
          >
            <span class="text-base shrink-0">{{ getFileIcon(file) }}</span>
            <span class="flex-1 truncate text-[13px]" :class="file.isDirectory ? 'font-medium text-hi' : 'text-mid'">{{ file.name }}</span>
            <UBadge v-if="file.isDirectory" tone="info" class="shrink-0">DIR</UBadge>
            <template v-else>
              <UBadge v-if="file.protected" tone="neutral" class="shrink-0" title="框架文件受保护">🔒</UBadge>
              <UButton
                v-if="file.deletable"
                size="sm"
                variant="ghost"
                class="!px-2 hover:!text-danger shrink-0"
                title="删除"
                @click.stop="deleteFileEntry(file)"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </UButton>
              <span v-if="isSupportedFile(file.name)" class="text-2xs text-low font-mono shrink-0">{{ file.size || '' }}</span>
              <UBadge v-if="!isSupportedFile(file.name)" class="shrink-0">不可预览</UBadge>
            </template>
          </div>

          <UEmpty v-if="!loading && files.length === 0" text="此目录为空" />
        </div>
      </div>

      <!-- 文件预览（PC 右栏） -->
      <div class="panel hidden lg:flex flex-col min-h-0 overflow-hidden">
        <template v-if="selectedFile">
          <div class="px-4 py-2.5 border-b border-line flex items-center justify-between shrink-0 gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-base shrink-0">{{ getFileIcon(selectedFile) }}</span>
              <div class="min-w-0">
                <h3 class="text-[13px] font-semibold text-hi truncate">{{ selectedFile.name }}</h3>
                <p class="text-2xs text-low font-mono truncate">{{ selectedFile.path }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <UButton
                v-if="canEdit(selectedFile) && fileType === 'text' && !editing"
                size="sm"
                variant="soft"
                @click="startEdit"
              >编辑</UButton>
              <button class="p-2 shrink-0 text-low hover:text-hi transition-colors" title="关闭预览" @click="closeFile">✕</button>
            </div>
          </div>
          <template v-if="editing">
            <FilePreview
              class="flex-1 min-h-0"
              type="text"
              :file-name="selectedFile?.name || ''"
              :content="editContent"
              editable
              @update:content="editContent = $event"
            />
            <div class="px-4 py-2.5 border-t border-line flex items-center justify-end gap-2 shrink-0">
              <UButton size="sm" @click="cancelEdit">取消</UButton>
              <UButton size="sm" variant="primary" :loading="editSaving" @click="saveEdit">保存</UButton>
            </div>
          </template>
          <FilePreview
            v-else
            class="flex-1 min-h-0"
            :type="fileType"
            :file-name="selectedFile?.name || ''"
            :content="fileContent"
            :image-data-url="imageDataUrl"
          />
        </template>
        <div v-else class="h-full flex items-center justify-center">
          <UEmpty icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" text="选择一个文件查看内容" />
        </div>
      </div>
    </div>

    <!-- 移动端文件预览抽屉（PC 上预览在右栏，不弹抽屉） -->
    <AppDrawer :open="!!selectedFile && isMobileViewport()" side="bottom" :title="selectedFile?.name" @update:open="closeFile">
      <div class="flex flex-col" :class="editing ? 'h-[70vh]' : 'h-[55vh]'">
        <div v-if="editing" class="flex items-center justify-end gap-2 px-3 py-2 border-b border-line shrink-0">
          <UButton size="sm" @click="cancelEdit">取消</UButton>
          <UButton size="sm" variant="primary" :loading="editSaving" @click="saveEdit">保存</UButton>
        </div>
        <FilePreview
          v-if="!editing"
          class="flex-1 min-h-0"
          :type="fileType"
          :file-name="selectedFile?.name || ''"
          :content="fileContent"
          :image-data-url="imageDataUrl"
        />
        <FilePreview
          v-else
          class="flex-1 min-h-0"
          type="text"
          :file-name="selectedFile?.name || ''"
          :content="editContent"
          editable
          @update:content="editContent = $event"
        />
      </div>
    </AppDrawer>

    <!-- 新建文件弹窗 -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showCreate" class="fixed inset-0 z-50 flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="showCreate = false">
          <div class="bg-panel border border-line w-full sm:max-w-sm flex flex-col max-h-[90dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">新建文件</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="showCreate = false">✕</button>
            </div>
            <div class="p-4 space-y-3.5">
              <div>
                <label class="block text-xs text-mid mb-1">目录</label>
                <p class="text-xs font-mono text-hi bg-sunken px-2.5 py-1.5 rounded-ctrl truncate">{{ currentPath === '.' ? '根目录（仅 .json）' : currentPath + '/' }}</p>
              </div>
              <div>
                <label class="block text-xs text-mid mb-1">文件名 <span class="text-danger">*</span></label>
                <input v-model="newName" type="text" class="input h-9 font-mono text-xs" placeholder="例如 my-config.json（支持 json/js/txt/m3u/conf）" @keyup.enter="createFile">
              </div>
              <div>
                <label class="block text-xs text-mid mb-1">初始内容</label>
                <textarea v-model="newContent" rows="4" class="input font-mono text-xs" spellcheck="false" />
              </div>
            </div>
            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton size="sm" @click="showCreate = false">取消</UButton>
              <UButton size="sm" variant="primary" :loading="createBusy" :disabled="!newName.trim()" @click="createFile">创建</UButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
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

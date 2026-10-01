<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { adminApi } from '../api/admin'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const emit = defineEmits(['changed'])
const toast = useToast()
const dialog = useDialog()

const cards = ref([])
const localOnly = ref([])
const errors = ref([])
const loading = ref(false)
const searchQuery = ref('')
const filterKey = ref('all')
const installingName = ref('')
const sources = ref([])
const ghProxy = ref('')
const showSources = ref(false)
const savingSources = ref(false)
const currentPlatform = ref('')

// 添加源弹窗（自定义弹窗输入，禁止原生 prompt）
const showAddSource = ref(false)
const addMode = ref('manual') // manual | config
const manualSource = ref('')
const addSourceError = ref('')
const configJsonFiles = ref([])
const selectedConfigFile = ref('')
const loadingConfigFiles = ref(false)

// GitHub 加速代理预设（docs/sub.md 与 controllers/github.js 中使用的公共代理）
const GITHUB_PROXIES = [
  'https://github.catvod.com/',
  'https://gh-proxy.playdreamer.cn/'
]

const STATUS_META = {
  not_installed: { label: '未安装', tone: 'neutral' },
  installed: { label: '已安装', tone: 'ok' },
  update_available: { label: '可更新', tone: 'warn' },
  local_only: { label: '本地未托管', tone: 'neutral' }
}

const FILTERS = [
  { key: 'all', label: '全部' },
  { key: 'updatable', label: '可更新' },
  { key: 'not_installed', label: '未安装' },
]

const filteredCards = computed(() => {
  let list = cards.value
  if (filterKey.value === 'updatable') list = list.filter(c => c.status === 'update_available')
  if (filterKey.value === 'not_installed') list = list.filter(c => c.status === 'not_installed')
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(c =>
      (c.name && c.name.toLowerCase().includes(q)) ||
      (c.title && c.title.toLowerCase().includes(q)) ||
      (c.desc && c.desc.toLowerCase().includes(q))
    )
  }
  return list
})

const fetchList = async (refresh = false) => {
  loading.value = true
  try {
    const res = await adminApi.getMarketList(refresh)
    if (res && res.success) {
      cards.value = res.data.plugins || []
      localOnly.value = res.data.localOnly || []
      errors.value = res.data.errors || []
      currentPlatform.value = res.data.currentPlatform || ''
      ghProxy.value = res.data.ghProxy || ''
    }
  } catch (error) {
    console.error('获取插件市场列表失败:', error)
    toast.error('获取插件市场列表失败')
  } finally {
    loading.value = false
  }
}

const fetchSources = async () => {
  try {
    const res = await adminApi.getMarketSources()
    if (res && res.success) {
      sources.value = res.data.sources || []
      ghProxy.value = res.data.ghProxy || ''
    }
  } catch (error) {
    console.error('获取市场源失败:', error)
  }
}

const saveSources = async () => {
  savingSources.value = true
  try {
    const res = await adminApi.saveMarketSources(sources.value, ghProxy.value)
    if (res && res.success) {
      toast.success('市场源已保存')
      showSources.value = false
      await fetchList(true)
    } else {
      toast.error('保存失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    toast.error('保存失败: ' + error.message)
  } finally {
    savingSources.value = false
  }
}

const addSource = () => {
  manualSource.value = ''
  selectedConfigFile.value = ''
  addSourceError.value = ''
  addMode.value = 'manual'
  showAddSource.value = true
}

const loadConfigJsonFiles = async () => {
  loadingConfigFiles.value = true
  try {
    const res = await adminApi.listDirectory('config')
    configJsonFiles.value = (res?.files || []).filter(f => !f.isDirectory && f.name.endsWith('.json'))
  } catch (error) {
    console.error('读取 config 目录失败:', error)
    configJsonFiles.value = []
  } finally {
    loadingConfigFiles.value = false
  }
}

const switchAddMode = (mode) => {
  addMode.value = mode
  addSourceError.value = ''
  if (mode === 'config' && configJsonFiles.value.length === 0) loadConfigJsonFiles()
}

const removeSource = (idx) => {
  sources.value.splice(idx, 1)
}

const confirmAddSource = () => {
  addSourceError.value = ''
  if (addMode.value === 'manual') {
    const v = manualSource.value.trim()
    if (!v) return
    if (sources.value.includes(v)) {
      addSourceError.value = '该源已存在'
      return
    }
    sources.value.push(v)
  } else {
    if (!selectedConfigFile.value) return
    const p = `config/${selectedConfigFile.value}`
    if (sources.value.includes(p)) {
      addSourceError.value = '该源已存在'
      return
    }
    sources.value.push(p)
  }
  showAddSource.value = false
}

// 安装/更新任务进度（轮询 /market/install/status）
const task = ref(null)
const taskTimer = ref(null)
const showTaskModal = ref(false)
const nowTick = ref(Date.now())
const logBox = ref(null)

// ==================== 上传插件安装 ====================
const showUpload = ref(false)
const uploadZip = ref(null) // File 对象
const uploadActive = ref(false)
const uploadStart = ref(false)
const uploadBusy = ref(false)
const uploadError = ref('')
const zipInput = ref(null)

const pickZip = () => zipInput.value?.click()

const onZipChosen = (e) => {
  uploadError.value = ''
  const file = e.target.files?.[0]
  e.target.value = '' // 允许重复选择同一文件
  if (!file) return
  if (!file.name.toLowerCase().endsWith('.zip')) {
    uploadError.value = '仅支持 .zip 安装包'
    return
  }
  uploadZip.value = file
}

const openUpload = () => {
  uploadZip.value = null
  uploadActive.value = false
  uploadStart.value = false
  uploadError.value = ''
  showUpload.value = true
}

const submitUpload = async () => {
  if (!uploadZip.value) return
  if (hasRunningTask.value) {
    toast.warning('已有安装任务进行中，请等待完成')
    return
  }
  uploadBusy.value = true
  uploadError.value = ''
  try {
    const res = await adminApi.uploadPlugin(uploadZip.value, { active: uploadActive.value, start: uploadStart.value })
    if (res && res.success) {
      showUpload.value = false
      uploadZip.value = null
      startTaskPolling() // 复用现有安装进度面板（轮询 /market/install/status）
    } else {
      uploadError.value = res?.error || '上传失败'
    }
  } catch (error) {
    uploadError.value = error.message
  } finally {
    uploadBusy.value = false
  }
}

const hasRunningTask = computed(() => task.value?.status === 'running')

// 恢复轮询：弹窗打开时由调用方触发；页面加载时若发现有 running 任务则静默轮询（横幅展示，不自动弹窗）
const ensurePolling = () => {
  if (taskTimer.value) return
  taskTimer.value = setInterval(pollTask, 1000)
  pollTask()
}

const startTaskPolling = () => {
  showTaskModal.value = true
  ensurePolling()
}

const pollTask = async () => {
  try {
    const res = await adminApi.getInstallStatus()
    if (res && res.success) {
      task.value = res.data
      nowTick.value = Date.now()
      if (res.data && res.data.status !== 'running') {
        clearInterval(taskTimer.value)
        taskTimer.value = null
        await fetchList()
        emit('changed')
      }
    }
  } catch (error) {
    console.error('查询安装进度失败:', error)
  }
}

const checkRunningTask = async () => {
  try {
    const res = await adminApi.getInstallStatus()
    if (res && res.success && res.data && res.data.status === 'running') {
      task.value = res.data
      ensurePolling()
    }
  } catch (error) {
    console.error('查询安装任务失败:', error)
  }
}

const closeTaskModal = () => {
  // running 时关闭弹窗仅收起界面，轮询继续（横幅可见），可随时从横幅唤起
  showTaskModal.value = false
}

// 日志窗口自动滚到底部
watch(() => task.value?.logs?.length, async () => {
  await nextTick()
  if (logBox.value) logBox.value.scrollTop = logBox.value.scrollHeight
})

// 步骤条：stage → 步骤序号；done 视为全部完成
const TASK_STEPS = [
  { key: 'download', label: '下载' },
  { key: 'verify', label: '校验' },
  { key: 'extract', label: '安装' }, // extract 与 register 归并为"安装"步
  { key: 'deps', label: '依赖' },
  { key: 'start', label: '启动' }
]
const STAGE_INDEX = { download: 0, verify: 1, extract: 2, register: 2, deps: 3, start: 4, done: 5 }

const taskStepState = (idx) => {
  const t = task.value
  if (!t) return 'pending'
  if (t.status === 'done') return 'done'
  const cur = STAGE_INDEX[t.stage] ?? 0
  if (t.status === 'error') return idx < cur ? 'done' : idx === cur ? 'error' : 'pending'
  return idx < cur ? 'done' : idx === cur ? 'active' : 'pending'
}

const taskElapsed = computed(() => {
  const t = task.value
  if (!t?.startedAt) return ''
  const end = t.finishedAt || nowTick.value
  const sec = Math.max(0, Math.round((end - t.startedAt) / 1000))
  return sec >= 60 ? `${Math.floor(sec / 60)}m${sec % 60}s` : `${sec}s`
})

const installCard = async (card) => {
  const ok = await dialog.confirm({
    title: `安装 ${card.title || card.name} ${card.version}`,
    message: '安装完成后将自动启用并启动该插件。',
    confirmText: '安装',
  })
  if (!ok) return
  installingName.value = card.name
  try {
    const res = await adminApi.installMarketPlugin(card.name, { active: true, start: true })
    if (res && res.success) {
      installingName.value = ''
      startTaskPolling()
      return
    }
    toast.error('安装失败: ' + (res?.error || '未知错误'))
  } catch (error) {
    toast.error('安装失败: ' + (error?.response?.data?.error || error.message))
  } finally {
    installingName.value = ''
  }
}

const updateCard = async (card) => {
  const ok = await dialog.confirm({
    title: `更新 ${card.name}`,
    message: `更新到 v${card.version}？运行中的插件会先停止并在更新后自动重启。`,
    confirmText: '更新',
  })
  if (!ok) return
  installingName.value = card.name
  try {
    const res = await adminApi.updateMarketPlugin(card.name)
    if (res && res.success) {
      installingName.value = ''
      startTaskPolling()
      return
    }
    toast.error('更新失败: ' + (res?.error || '未知错误'))
  } catch (error) {
    toast.error('更新失败: ' + (error?.response?.data?.error || error.message))
  } finally {
    installingName.value = ''
  }
}

const uninstallCard = async (card) => {
  const ok = await dialog.confirm({
    title: `卸载 ${card.name}`,
    message: `插件目录 plugins/${card.name} 及其运行数据将被删除，该操作不可恢复。`,
    danger: true,
    confirmText: '卸载',
  })
  if (!ok) return
  installingName.value = card.name
  try {
    const res = await adminApi.uninstallMarketPlugin(card.name)
    if (res && res.success) {
      toast.success(`${card.name} 已卸载`)
      await fetchList()
      emit('changed')
    } else {
      toast.error('卸载失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    toast.error('卸载失败: ' + (error?.response?.data?.error || error.message))
  } finally {
    installingName.value = ''
  }
}

onMounted(() => {
  fetchList()
  fetchSources()
  checkRunningTask()
})
</script>

<template>
  <div class="market-content flex flex-col h-full min-h-0">
    <!-- 工具栏 -->
    <div class="flex flex-wrap items-center gap-2 mb-3 shrink-0">
      <div class="flex rounded-ctrl overflow-hidden border border-line text-xs shrink-0">
        <button
          v-for="(f, i) in FILTERS" :key="f.key"
          class="px-3 h-9 transition-colors"
          :class="[
            i > 0 ? 'border-l border-line' : '',
            filterKey === f.key ? 'bg-accent-dim text-accent' : 'text-mid hover:text-hi hover:bg-mid/5'
          ]"
          @click="filterKey = f.key"
        >{{ f.label }}</button>
      </div>

      <div class="relative flex-1 min-w-[140px] sm:max-w-xs">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="w-3.5 h-3.5 text-low" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input v-model="searchQuery" type="text" placeholder="搜索插件…" class="input h-9 pl-9 pr-8 w-full">
        <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
      </div>

      <UButton size="sm" :loading="loading" @click="fetchList(true)">
        <svg v-if="!loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        刷新
      </UButton>
      <UButton size="sm" @click="openUpload" title="上传本地 zip 安装包">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
        上传插件
      </UButton>
      <UButton size="sm" @click="showSources = true">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        市场源
      </UButton>
    </div>

    <!-- 进行中任务横幅（后台运行后可随时唤起进度） -->
    <div v-if="task && task.status === 'running' && !showTaskModal"
         class="mb-3 px-3 py-2 bg-accent-dim border border-accent/30 rounded-ctrl flex items-center gap-2.5 shrink-0">
      <svg class="w-4 h-4 text-accent animate-spin shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
      </svg>
      <span class="text-xs text-accent flex-1 truncate">
        正在{{ task.type === 'update' ? '更新' : '安装' }} {{ task.name }} v{{ task.version }}
        <span v-if="task.percent > 0" class="font-mono">（{{ task.percent }}%）</span> — {{ task.message }}
      </span>
      <UButton size="sm" variant="primary" @click="startTaskPolling">查看进度</UButton>
    </div>

    <!-- 源不可达提示 -->
    <div v-if="errors.length" class="mb-3 px-3 py-2 bg-warn/10 border border-warn/25 rounded-ctrl text-xs text-warn shrink-0">
      <span class="font-medium">部分市场源不可达：</span>
      <span v-for="(e, i) in errors" :key="i" class="block truncate font-mono text-2xs mt-0.5">{{ e.source }} — {{ e.error }}</span>
    </div>

    <!-- 列表 -->
    <div class="flex-1 overflow-y-auto min-h-0 -mx-1 px-1">
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3 pt-1">
        <USkeleton v-for="i in 4" :key="i" variant="panel" />
      </div>

      <UEmpty v-else-if="filteredCards.length === 0" class="panel mt-1" text="市场暂无匹配的插件">
        <UButton size="sm" @click="showSources = true">检查市场源设置</UButton>
      </UEmpty>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3 enter-stagger">
        <div
          v-for="(card, idx) in filteredCards" :key="card.name"
          class="panel p-3.5 flex flex-col gap-1.5 relative overflow-hidden"
          :style="{ '--i': Math.min(idx, 8) }"
        >
          <!-- 状态左缘条 -->
          <span class="absolute left-0 top-0 bottom-0 w-[3px]" :class="{
            'bg-warn': card.status === 'update_available',
            'bg-ok': card.status === 'installed',
            'bg-low/30': card.status !== 'installed' && card.status !== 'update_available',
          }" />

          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <StatusDot :tone="card.running ? 'running' : 'off'" />
                <h3 class="font-semibold text-[13px] text-hi truncate" :title="card.title">{{ card.title || card.name }}</h3>
              </div>
              <div class="text-2xs text-low font-mono mt-0.5">{{ card.name }}</div>
            </div>
            <UBadge :tone="STATUS_META[card.status]?.tone">{{ STATUS_META[card.status]?.label || card.status }}</UBadge>
          </div>

          <p class="text-xs text-mid line-clamp-2 min-h-[2.2em]" :title="card.desc">{{ card.desc || '暂无描述' }}</p>

          <div class="flex items-center gap-1.5 flex-wrap text-2xs font-mono">
            <UBadge tone="info">{{ card.runtime === 'node' ? 'Node 服务' : card.runtime === 'python' ? 'Python 服务' : '二进制' }}</UBadge>
            <span class="text-low">v{{ card.version }}</span>
            <span v-if="card.installedVersion && card.installedVersion !== card.version" class="text-low">已装 v{{ card.installedVersion }}</span>
            <span v-if="card.author" class="text-low">@{{ card.author }}</span>
            <span v-if="card.running" class="text-ok">{{ card.pid }}</span>
          </div>

          <p v-if="!card.platformSupported" class="text-2xs text-warn">⚠ 当前平台 ({{ currentPlatform }}) 可能无可用二进制</p>

          <div class="flex gap-1.5 pt-1.5 mt-auto">
            <UButton
              v-if="card.status === 'not_installed'"
              variant="primary" class="flex-1" :disabled="hasRunningTask"
              :loading="installingName === card.name || (hasRunningTask && task.name === card.name)"
              :title="hasRunningTask && task.name !== card.name ? `正在${task.type === 'update' ? '更新' : '安装'} ${task.name}，请等待完成` : ''"
              @click="installCard(card)"
            >
              {{ hasRunningTask && task.name === card.name ? '安装中…' : '安装' }}
            </UButton>
            <UButton
              v-else-if="card.status === 'update_available'"
              variant="primary" class="flex-1" :disabled="hasRunningTask"
              :loading="installingName === card.name || (hasRunningTask && task.name === card.name)"
              @click="updateCard(card)"
            >
              {{ hasRunningTask && task.name === card.name ? '更新中…' : `更新到 v${card.version}` }}
            </UButton>
            <span v-else-if="card.status === 'installed'" class="flex-1 h-10 inline-flex items-center justify-center rounded-ctrl bg-ok/10 text-ok border border-ok/25 text-xs cursor-default">✓ 已安装</span>
            <span v-else class="flex-1 h-10 inline-flex items-center justify-center rounded-ctrl bg-mid/5 text-low border border-line text-xs cursor-not-allowed" title="本地已存在同名未托管目录，无法通过市场安装">本地已占用</span>
            <UButton
              v-if="card.status === 'installed' || card.status === 'update_available'"
              variant="danger" :disabled="hasRunningTask" :loading="installingName === card.name"
              title="卸载" @click="uninstallCard(card)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </UButton>
          </div>
        </div>
      </div>

      <!-- 本地未托管插件 -->
      <div v-if="localOnly.length" class="mt-3 px-3 py-2 bg-sunken rounded-ctrl text-2xs text-low">
        本地未托管插件：{{ localOnly.map(p => p.name).join('、') }}（手工放置的目录，市场不管理其版本）
      </div>
    </div>

    <!-- ============ 市场源设置抽屉 ============ -->
    <AppDrawer v-model:open="showSources" side="right" title="市场源设置">
      <div class="p-4 space-y-4">
        <!-- 市场源列表 -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="eyebrow">// 市场源列表</div>
            <UButton size="sm" variant="soft" @click="addSource">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              添加源
            </UButton>
          </div>
          <div v-for="(src, idx) in sources" :key="idx" class="flex items-center gap-2">
            <UBadge :tone="src.startsWith('http') ? 'info' : 'neutral'" class="shrink-0">{{ src.startsWith('http') ? '远程' : '本地' }}</UBadge>
            <input v-model="sources[idx]" type="text" class="input flex-1 h-8 text-2xs font-mono" spellcheck="false">
            <button class="p-1.5 text-low hover:text-danger transition-colors shrink-0" title="删除" @click="removeSource(idx)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
          <div v-if="!sources.length" class="text-xs text-low py-1">暂无市场源，点击「添加源」开始配置</div>
        </div>

        <!-- GitHub 加速代理（独立设置） -->
        <div class="border-t border-line pt-3.5 space-y-1.5">
          <div class="flex items-center justify-between">
            <div class="eyebrow">// GitHub 加速代理</div>
            <button v-if="ghProxy" class="text-2xs text-low hover:text-danger transition-colors" @click="ghProxy = ''">清空</button>
          </div>
          <p class="text-2xs text-low leading-relaxed">GitHub 系链接（github.com / raw.githubusercontent.com 等）直连失败时，自动追加此前缀重试；市场源（market.json）拉取失败时同样走此代理重试。留空表示仅直连。</p>
          <input v-model="ghProxy" type="text" placeholder="留空 = 直连，例如 https://github.catvod.com/" class="input h-8 text-2xs font-mono" spellcheck="false">
          <div class="flex items-center gap-1.5 flex-wrap">
            <button
              v-for="p in GITHUB_PROXIES" :key="p"
              class="px-2 py-1 rounded-ctrl text-2xs font-mono border transition-colors"
              :class="ghProxy === p ? 'border-accent/60 bg-accent-dim text-accent' : 'border-line text-low hover:text-accent hover:border-accent/40'"
              @click="ghProxy = p"
            >{{ p.replace('https://', '').replace('/', '') }}</button>
          </div>
        </div>
      </div>

      <!-- 抽屉底部动作栏 -->
      <div class="sticky bottom-0 bg-panel border-t border-line px-4 py-3 pb-safe">
        <UButton variant="primary" block :loading="savingSources" @click="saveSources">保存配置</UButton>
      </div>
    </AppDrawer>

    <!-- ============ 添加市场源弹窗（z 高于市场源抽屉 z-[80]，二级弹窗浮于抽屉之上） ============ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showAddSource" class="fixed inset-0 z-[85] flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="showAddSource = false">
          <div class="bg-panel border border-line w-full sm:max-w-md flex flex-col max-h-[90dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">添加市场源</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="showAddSource = false">✕</button>
            </div>

            <div class="p-4 space-y-3.5 overflow-y-auto">
              <!-- 方式切换 -->
              <div class="flex rounded-ctrl overflow-hidden border border-line text-xs">
                <button class="flex-1 h-10 flex items-center justify-center gap-1.5 transition-colors"
                        :class="addMode === 'manual' ? 'bg-accent-dim text-accent' : 'text-mid hover:bg-mid/5'"
                        @click="switchAddMode('manual')">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.102-1.101m0 0l3.122-3.122a4 4 0 015.656 0M14 10l-4 4" /></svg>
                  手动输入链接
                </button>
                <button class="flex-1 h-10 flex items-center justify-center gap-1.5 border-l border-line transition-colors"
                        :class="addMode === 'config' ? 'bg-accent-dim text-accent' : 'text-mid hover:bg-mid/5'"
                        @click="switchAddMode('config')">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                  选择 config 目录配置
                </button>
              </div>

              <!-- 手动输入 -->
              <div v-if="addMode === 'manual'" class="space-y-1.5">
                <label class="block text-xs text-mid">市场源地址</label>
                <input v-model="manualSource" type="text" autofocus placeholder="https://example.com/market.json"
                       class="input h-9 text-xs font-mono" autocomplete="off" spellcheck="false" @keyup.enter="confirmAddSource">
                <p class="text-2xs text-low">支持 HTTP(S) 地址的 market.json 清单文件</p>
              </div>

              <!-- 从 config 目录选择 -->
              <div v-else class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <label class="block text-xs text-mid">选择 JSON 配置文件</label>
                  <button class="text-2xs text-accent flex items-center gap-0.5" :disabled="loadingConfigFiles" @click="loadConfigJsonFiles">
                    <svg class="w-3 h-3" :class="{ 'animate-spin': loadingConfigFiles }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    刷新
                  </button>
                </div>
                <USelect v-model="selectedConfigFile" :options="[{ label: loadingConfigFiles ? '加载中…' : '请选择 config 目录下的 .json 文件', value: '' }, ...configJsonFiles.map(f => ({ label: `${f.path} (${f.size} bytes)`, value: f.name }))]" />
                <p class="text-2xs text-low">文件需为 market.json 格式（含 plugins 数组）。选择后以 config/xxx.json 本地源方式添加。</p>
              </div>

              <p v-if="addSourceError" class="text-xs text-danger">{{ addSourceError }}</p>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton size="sm" @click="showAddSource = false">取消</UButton>
              <UButton size="sm" variant="primary" :disabled="addMode === 'manual' ? !manualSource.trim() : !selectedConfigFile" @click="confirmAddSource">添加</UButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ 上传插件弹窗 ============ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showUpload" class="fixed inset-0 z-[85] flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="showUpload = false">
          <div class="bg-panel border border-line w-full sm:max-w-sm flex flex-col max-h-[90dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">上传插件安装包</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="showUpload = false">✕</button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3.5">
              <div>
                <label class="block text-xs text-mid mb-1">安装包（.zip） <span class="text-danger">*</span></label>
                <input ref="zipInput" type="file" accept=".zip" class="hidden" @change="onZipChosen">
                <button
                  class="w-full h-16 rounded-ctrl border border-dashed border-line hover:border-accent/50 hover:bg-accent/[0.04] transition-colors flex flex-col items-center justify-center gap-1"
                  @click="pickZip"
                >
                  <svg class="w-4 h-4 text-low" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                  <span v-if="uploadZip" class="text-xs text-hi font-mono truncate max-w-full px-3">{{ uploadZip.name }}</span>
                  <span v-else class="text-xs text-low">点击选择 zip 安装包</span>
                </button>
                <p class="mt-1.5 text-2xs text-low">插件名与版本从包内 plugin.json 读取；无清单的上游原始包会自动剥壳并按文件名登记。同名已安装插件将被覆盖安装。</p>
              </div>

              <div class="flex items-center justify-between p-2.5 bg-sunken rounded-ctrl border border-line">
                <span class="text-xs text-mid">随服务启动</span>
                <USwitch v-model="uploadActive" />
              </div>
              <div class="flex items-center justify-between p-2.5 bg-sunken rounded-ctrl border border-line">
                <span class="text-xs text-mid">安装完成后立即启动</span>
                <USwitch v-model="uploadStart" />
              </div>

              <p v-if="uploadError" class="text-xs text-danger break-all">{{ uploadError }}</p>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton size="sm" class="flex-1 sm:flex-none" @click="showUpload = false">取消</UButton>
              <UButton size="sm" variant="primary" class="flex-1 sm:flex-none" :loading="uploadBusy" :disabled="!uploadZip" @click="submitUpload">上传安装</UButton>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============ 安装/更新管线进度弹窗 ============ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showTaskModal && task" class="fixed inset-0 z-[60] flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="task.status !== 'running' && (showTaskModal = false)">
          <div class="bg-panel border border-line w-full sm:max-w-sm flex flex-col rounded-t-panel sm:rounded-panel overflow-hidden" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">
                {{ task.type === 'update' ? '更新插件' : '安装插件' }}
                <span class="font-mono text-accent">{{ task.name }}</span>
                <span class="font-mono text-xs text-low">v{{ task.version }}</span>
              </h3>
              <span class="text-2xs font-mono text-low" :class="{ 'animate-pulse': task.status === 'running' }">{{ taskElapsed }}</span>
            </div>

            <div class="p-4 space-y-3.5">
              <!-- 主进度条 -->
              <div class="h-1.5 rounded-full bg-sunken overflow-hidden">
                <div v-if="task.status === 'done'" class="h-full bg-ok transition-all" style="width: 100%" />
                <div v-else-if="task.status === 'error'" class="h-full bg-danger" style="width: 100%" />
                <div v-else-if="task.percent > 0" class="h-full bg-accent transition-all duration-300" :style="{ width: task.percent + '%' }" />
                <div v-else class="h-full w-1/3 bg-accent/60 animate-pulse" />
              </div>
              <div class="flex justify-between text-2xs gap-3">
                <span class="text-mid truncate">{{ task.message || '…' }}</span>
                <span v-if="task.percent > 0 && task.status === 'running'" class="font-mono text-low shrink-0">{{ task.percent }}%</span>
              </div>

              <!-- 管线步骤可视化 -->
              <div class="flex items-center justify-between px-0.5 pt-1">
                <template v-for="(step, i) in TASK_STEPS" :key="step.key">
                  <div class="flex flex-col items-center gap-1 flex-1">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold font-mono"
                         :class="{
                           'bg-ok text-white': taskStepState(i) === 'done',
                           'bg-accent text-[#04211d]': taskStepState(i) === 'active',
                           'bg-danger text-white': taskStepState(i) === 'error',
                           'bg-mid/15 text-low': taskStepState(i) === 'pending',
                         }">
                      <svg v-if="taskStepState(i) === 'done'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                      <svg v-else-if="taskStepState(i) === 'active'" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 12a8 8 0 018-8" /></svg>
                      <svg v-else-if="taskStepState(i) === 'error'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                      <template v-else>{{ i + 1 }}</template>
                    </div>
                    <span class="text-2xs" :class="taskStepState(i) === 'pending' ? 'text-low' : 'text-mid'">{{ step.label }}</span>
                  </div>
                  <div v-if="i < TASK_STEPS.length - 1" class="h-px flex-1 -mt-4" :class="taskStepState(i) === 'done' ? 'bg-ok/60' : 'bg-line'" />
                </template>
              </div>

              <!-- 依赖安装实时日志（保留最近输出，自动滚动） -->
              <div v-if="task.logs && task.logs.length" ref="logBox"
                   class="px-2.5 py-2 bg-sunken border border-line rounded-ctrl text-2xs font-mono text-ok leading-relaxed h-24 overflow-y-auto sunken-scroll">
                <div v-for="(line, i) in task.logs" :key="i" class="whitespace-nowrap overflow-hidden text-ellipsis">$ {{ line }}</div>
              </div>

              <!-- 结果 / 错误 -->
              <div v-if="task.status === 'done'" class="text-xs rounded-ctrl px-3 py-2 border"
                   :class="task.result?.error ? 'bg-warn/10 text-warn border-warn/25' : 'bg-ok/10 text-ok border-ok/25'">
                <template v-if="task.result?.started">✓ 插件已启动 (pid={{ task.result.pid }})</template>
                <template v-else-if="task.result?.error">安装完成，但启动失败：{{ task.result.error }}，可到「插件管理」页手动启动</template>
                <template v-else>✓ {{ task.type === 'update' ? '更新' : '安装' }}完成</template>
              </div>
              <div v-else-if="task.status === 'error'" class="text-xs bg-danger/10 text-danger border border-danger/25 rounded-ctrl px-3 py-2 break-all">
                {{ task.error }}
              </div>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton v-if="task.status === 'running'" size="sm" @click="closeTaskModal">后台运行</UButton>
              <UButton v-else size="sm" variant="primary" @click="closeTaskModal">完成</UButton>
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

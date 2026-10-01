<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '../api/admin'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'
import PluginMarket from './PluginMarket.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const dialog = useDialog()

// tab 跟随路由：/plugins/market 为市场，其余为已安装列表（刷新/直链均保持）
const activeTab = computed(() => (route.path === '/plugins/market' ? 'market' : 'installed'))
const goTab = (tab) => router.push(tab === 'market' ? '/plugins/market' : '/plugins')

const runtimeStatus = ref({})
const togglingName = ref('')
const deletingName = ref('')

const plugins = ref([])
const originalPlugins = ref([])
const loading = ref(false)
const saving = ref(false)
const isDefault = ref(false)
const searchQuery = ref('')

const hasUnsavedChanges = computed(() => {
  return JSON.stringify(plugins.value) !== JSON.stringify(originalPlugins.value)
})

// 编辑弹窗状态
const showModal = ref(false)
const isEditing = ref(false)
const editingIndex = ref(-1)
const formError = ref('')
const currentPlugin = ref({
  name: '',
  path: '',
  params: '',
  desc: '',
  active: false,
  env: {}
})

// 环境变量编辑行（弹窗内以 [{key, value}] 数组编辑，保存时归一化为对象）
const envRows = ref([])

const envPreview = (env) => Object.entries(env || {}).map(([k, v]) => `${k}=${v}`).join('  ')

const addEnvRow = () => envRows.value.push({ key: '', value: '' })
const removeEnvRow = (i) => envRows.value.splice(i, 1)

// envRows → 对象：剔除空 key；全空则删除 env 字段（不污染无环境变量的插件条目）
const normalizeEnv = () => {
  const obj = {}
  for (const row of envRows.value) {
    const k = (row.key || '').trim()
    if (k) obj[k] = row.value ?? ''
  }
  if (Object.keys(obj).length === 0) {
    delete currentPlugin.value.env
  } else {
    currentPlugin.value.env = obj
  }
}

const fetchPlugins = async () => {
  loading.value = true
  try {
    const res = await adminApi.getPlugins()
    if (res && res.success) {
      plugins.value = res.data || []
      originalPlugins.value = JSON.parse(JSON.stringify(res.data || []))
      isDefault.value = res.isDefault || false
    }
  } catch (error) {
    console.error('获取插件列表失败:', error)
    toast.error('获取插件列表失败')
  } finally {
    loading.value = false
  }
}

const fetchStatus = async () => {
  try {
    const res = await adminApi.getPluginStatus()
    if (res && res.success) runtimeStatus.value = res.data || {}
  } catch (error) {
    console.error('获取运行状态失败:', error)
  }
}

const isRunning = (name) => !!runtimeStatus.value[name]?.running

const toggleRuntime = async (name, action) => {
  togglingName.value = name
  try {
    const fn = action === 'start' ? adminApi.startPlugin
      : action === 'stop' ? adminApi.stopPlugin : adminApi.restartPlugin
    const res = await fn(name)
    if (res && res.success) {
      toast.success((action === 'start' ? '已启动 ' : action === 'stop' ? '已停止 ' : '已重启 ') + name)
      await fetchStatus()
    } else {
      toast.error((action === 'start' ? '启动' : action === 'stop' ? '停止' : '重启') + '失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    toast.error('操作失败: ' + (error?.response?.data?.error || error.message))
  } finally {
    togglingName.value = ''
  }
}

const onMarketChanged = async () => {
  await fetchPlugins()
  await fetchStatus()
}

const savePlugins = async () => {
  saving.value = true
  try {
    const res = await adminApi.savePlugins(plugins.value)
    if (res && res.success) {
      toast.success('插件配置已保存')
      originalPlugins.value = JSON.parse(JSON.stringify(plugins.value))
      isDefault.value = false
      await fetchPlugins()
    } else {
      toast.error('保存失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    console.error('保存失败:', error)
    toast.error('保存失败: ' + error.message)
  } finally {
    saving.value = false
  }
}

const restoreDefaults = async () => {
  const ok = await dialog.confirm({
    title: '恢复默认配置',
    message: '将覆盖当前的所有插件配置修改，且不可撤销。确定继续吗？',
    danger: true,
    confirmText: '恢复默认',
  })
  if (!ok) return

  loading.value = true
  try {
    const res = await adminApi.restorePlugins()
    if (res && res.success) {
      toast.success('已恢复默认配置')
      await fetchPlugins()
    } else {
      toast.error('恢复失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    console.error('恢复失败:', error)
    toast.error('恢复失败: ' + error.message)
  } finally {
    loading.value = false
  }
}

const filteredPlugins = computed(() => {
  if (!searchQuery.value) return plugins.value
  const query = searchQuery.value.toLowerCase()
  return plugins.value.filter(p =>
    (p.name && p.name.toLowerCase().includes(query)) ||
    (p.desc && p.desc.toLowerCase().includes(query))
  )
})

const openAddModal = () => {
  isEditing.value = false
  editingIndex.value = -1
  formError.value = ''
  currentPlugin.value = {
    name: '',
    path: '',
    params: '',
    desc: '',
    active: false,
    env: {}
  }
  envRows.value = []
  showModal.value = true
}

const openEditModal = (plugin, index) => {
  isEditing.value = true
  editingIndex.value = index
  formError.value = ''
  currentPlugin.value = { ...plugin }
  envRows.value = Object.entries(plugin.env || {}).map(([key, value]) => ({ key, value: String(value) }))
  showModal.value = true
}

const saveModal = () => {
  formError.value = ''
  if (!currentPlugin.value.name || !currentPlugin.value.path) {
    formError.value = '插件名称和路径不能为空'
    return
  }
  normalizeEnv()

  if (isEditing.value) {
    plugins.value[editingIndex.value] = { ...currentPlugin.value }
  } else {
    // Check if name already exists
    if (plugins.value.some(p => p.name === currentPlugin.value.name)) {
      formError.value = `插件名称「${currentPlugin.value.name}」已存在`
      return
    }
    plugins.value.push({ ...currentPlugin.value })
  }

  showModal.value = false
}

const deletePlugin = async (plugin) => {
  // 删除 = 仅移除插件配置并停止相关进程，plugins/ 目录内的安装内容与数据一律保留。
  // 彻底卸载（删目录）请到「插件市场」对应卡片的卸载按钮。
  const ok = await dialog.confirm({
    title: `删除插件 ${plugin.name}`,
    message: '将停止运行中的插件并从配置移除，plugins/ 目录内容保留。删除后需点击「保存配置」落盘；如需彻底卸载请到插件市场操作。',
    danger: true,
    confirmText: '删除配置',
  })
  if (!ok) return
  // 运行中先停止（立即生效，不依赖保存配置）
  if (isRunning(plugin.name)) {
    deletingName.value = plugin.name
    try {
      await adminApi.stopPlugin(plugin.name)
      await fetchStatus()
    } catch (error) {
      toast.error('停止插件失败: ' + (error?.response?.data?.error || error.message))
    } finally {
      deletingName.value = ''
    }
  }
  plugins.value.splice(plugins.value.indexOf(plugin), 1)
}

const toggleActive = (index) => {
  plugins.value[index].active = !plugins.value[index].active
}

onMounted(() => {
  fetchPlugins()
  fetchStatus()
})
</script>

<template>
  <div class="plugins-page h-full flex flex-col">
    <PageHeader :eyebrow="activeTab === 'market' ? '// PLUGIN MARKET' : '// PLUGINS'" :title="activeTab === 'market' ? '插件市场' : '插件管理'" desc="管理系统插件配置 (.plugins.js)">
      <template #actions>
        <UBadge v-if="isDefault" tone="warn">默认配置</UBadge>
        <span v-if="hasUnsavedChanges" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-warn/10 text-warn border border-warn/30 rounded-full text-2xs">
          <span class="w-1.5 h-1.5 rounded-full bg-warn animate-pulse" />
          未保存
        </span>
        <UButton v-if="activeTab !== 'market'" size="sm" variant="primary" @click="goTab('market')">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
          插件市场
        </UButton>
      </template>
    </PageHeader>

    <UTabs
      class="mb-4 shrink-0"
      :model-value="activeTab"
      :tabs="[{ key: 'installed', label: '已安装', count: plugins.length }, { key: 'market', label: '市场' }]"
      @update:model-value="goTab($event === 'market' ? 'market' : 'installed')"
    />

    <!-- ============ 已安装插件 ============ -->
    <div v-if="activeTab === 'installed'" class="flex-1 flex flex-col min-h-0">
      <!-- 工具行：搜索 + 保存/恢复 -->
      <div class="flex flex-wrap items-center gap-2 mb-3 shrink-0">
        <div class="relative flex-1 min-w-[180px] sm:max-w-xs">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-3.5 h-3.5 text-low" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜索名称或描述…"
            class="input h-9 pl-9 pr-8"
          >
          <button
            v-if="searchQuery"
            class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi"
            @click="searchQuery = ''"
          >✕</button>
        </div>
        <div class="flex-1" />
        <UButton size="sm" @click="openAddModal">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          添加
        </UButton>
        <UButton size="sm" variant="primary" :loading="saving" :disabled="!hasUnsavedChanges" @click="savePlugins">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
          保存配置
        </UButton>
        <UButton size="sm" :disabled="loading" @click="restoreDefaults">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          恢复默认
        </UButton>
      </div>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3">
        <USkeleton v-for="i in 4" :key="i" variant="panel" />
      </div>

      <UEmpty v-else-if="filteredPlugins.length === 0" class="panel" :text="searchQuery ? '没有找到匹配的插件' : '还没有插件，去插件市场逛逛'">
        <UButton size="sm" variant="primary" @click="goTab('market')">进入插件市场</UButton>
      </UEmpty>

      <div v-else class="flex-1 overflow-y-auto -mx-1 px-1 enter-stagger">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3">
          <div
            v-for="(plugin, index) in filteredPlugins"
            :key="plugin.name"
            class="panel p-3.5 flex flex-col relative overflow-hidden group"
            :style="{ '--i': Math.min(index, 8) }"
          >
            <!-- active 状态左缘条 -->
            <span class="absolute left-0 top-0 bottom-0 w-[3px]" :class="plugin.active ? 'bg-ok' : 'bg-low/30'" />

            <div class="flex justify-between items-start gap-2 mb-1.5">
              <div class="flex items-center gap-2 overflow-hidden flex-1 min-w-0">
                <StatusDot :tone="isRunning(plugin.name) ? 'running' : 'off'" />
                <h3 class="font-semibold text-[13px] text-hi truncate" :title="plugin.name">{{ plugin.name }}</h3>
                <UBadge v-if="plugin.installedVersion" tone="info" :title="plugin.marketManaged ? '市场托管插件' : '已安装版本'">v{{ plugin.installedVersion }}</UBadge>
              </div>
              <USwitch :model-value="plugin.active" @update:model-value="toggleActive(plugins.indexOf(plugin))" />
            </div>

            <p class="text-xs text-mid mb-2.5 flex-1 line-clamp-1" :title="plugin.desc">{{ plugin.desc || '暂无描述' }}</p>

            <div class="text-2xs bg-sunken px-2 py-1.5 rounded-ctrl space-y-1 mb-2.5 font-mono">
              <div class="flex items-center gap-1.5">
                <span class="text-low shrink-0 w-8">路径</span>
                <span class="text-mid truncate" :title="plugin.path">{{ plugin.path }}</span>
              </div>
              <div v-if="plugin.params" class="flex items-center gap-1.5">
                <span class="text-low shrink-0 w-8">参数</span>
                <span class="text-mid truncate" :title="plugin.params">{{ plugin.params }}</span>
              </div>
              <div v-if="plugin.env && Object.keys(plugin.env).length" class="flex items-center gap-1.5">
                <span class="text-low shrink-0 w-8">环境</span>
                <span class="text-mid truncate" :title="envPreview(plugin.env)">{{ envPreview(plugin.env) }}</span>
              </div>
            </div>

            <!-- 操作行：触控目标 ≥ 40px -->
            <div class="flex items-center gap-1.5">
              <template v-if="isRunning(plugin.name)">
                <UButton size="sm" variant="soft" class="flex-1" :loading="togglingName === plugin.name" @click="toggleRuntime(plugin.name, 'stop')">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="1" /></svg>
                  停止
                </UButton>
              </template>
              <template v-else>
                <UButton size="sm" variant="soft" class="flex-1" :loading="togglingName === plugin.name" @click="toggleRuntime(plugin.name, 'start')">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                  启动
                </UButton>
              </template>
              <UButton size="sm" @click="openEditModal(plugin, plugins.indexOf(plugin))" title="编辑">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </UButton>
              <UButton size="sm" variant="danger" :loading="deletingName === plugin.name" @click="deletePlugin(plugin)" title="删除配置">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </UButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ 插件市场 ============ -->
    <div v-else class="flex-1 min-h-0 flex flex-col">
      <PluginMarket @changed="onMarketChanged" class="h-full" />
    </div>

    <!-- 编辑弹窗：移动端 bottom sheet / PC 居中模态 -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-50 flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="showModal = false">
          <div class="bg-panel border border-line w-full sm:max-w-sm flex flex-col max-h-[90dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">{{ isEditing ? '编辑插件' : '添加插件' }}</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="showModal = false">✕</button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3.5">
              <div>
                <div class="flex justify-between items-baseline mb-1">
                  <label class="block text-xs text-mid">插件名称 <span class="text-danger">*</span></label>
                  <span v-if="isEditing" class="text-2xs text-low">不可修改</span>
                </div>
                <input v-model="currentPlugin.name" type="text" class="input h-9 font-mono text-xs" placeholder="例如: req-proxy" :disabled="isEditing" autocomplete="off" spellcheck="false">
              </div>

              <div>
                <label class="block text-xs text-mid mb-1">插件路径 <span class="text-danger">*</span></label>
                <input v-model="currentPlugin.path" type="text" class="input h-9 font-mono text-xs" placeholder="例如: plugins/req-proxy" autocomplete="off" spellcheck="false">
              </div>

              <div>
                <label class="block text-xs text-mid mb-1">启动参数</label>
                <input v-model="currentPlugin.params" type="text" class="input h-9 font-mono text-xs" placeholder="例如: -p 57571" autocomplete="off" spellcheck="false">
              </div>

              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="block text-xs text-mid">环境变量</label>
                  <button class="text-2xs text-accent hover:brightness-125 flex items-center gap-0.5" @click="addEnvRow">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    添加变量
                  </button>
                </div>
                <div v-for="(row, i) in envRows" :key="i" class="flex items-center gap-1.5 mb-1.5">
                  <input v-model="row.key" type="text" placeholder="变量名，如 PORT" class="input flex-1 h-8 text-2xs font-mono" spellcheck="false">
                  <span class="text-low shrink-0 font-mono">=</span>
                  <input v-model="row.value" type="text" placeholder="值" class="input flex-1 h-8 text-2xs font-mono" spellcheck="false">
                  <button class="p-1.5 text-low hover:text-danger transition-colors shrink-0" title="删除" @click="removeEnvRow(i)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
                <div v-if="!envRows.length" class="text-2xs text-low py-0.5">
                  无环境变量。启动时注入到插件进程（Node 型插件常用，如 PORT=9527）
                </div>
              </div>

              <div>
                <label class="block text-xs text-mid mb-1">描述</label>
                <textarea v-model="currentPlugin.desc" rows="2" class="input resize-none" placeholder="简短描述…" />
              </div>

              <div class="flex items-center justify-between p-2.5 bg-sunken rounded-ctrl border border-line">
                <span class="text-xs text-mid">启动服务时自动运行</span>
                <USwitch v-model="currentPlugin.active" />
              </div>

              <p v-if="formError" class="text-xs text-danger">{{ formError }}</p>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton size="sm" class="flex-1 sm:flex-none" @click="showModal = false">取消</UButton>
              <UButton size="sm" variant="primary" class="flex-1 sm:flex-none" @click="saveModal">确定</UButton>
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

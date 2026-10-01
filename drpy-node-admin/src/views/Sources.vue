<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSystemStore } from '../stores/system'
import { spiderApi } from '../api/spider'
import { adminApi } from '../api/admin'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const router = useRouter()
const systemStore = useSystemStore()
const toast = useToast()
const dialog = useDialog()

const validating = ref(null)
const validationResults = ref({})
const flowVerifying = ref(null)
const flowVerifyPlay = ref(false)

const VERDICT_META = {
  healthy: { label: '可用', tone: 'ok' },
  partial: { label: '部分可用', tone: 'warn' },
  dead: { label: '不可用', tone: 'danger' },
  not_supported: { label: '不支持', tone: 'neutral' },
}

// 启用/停用状态：disabled 清单由 /sources 接口返回、随列表刷新（单一数据流）
const disabledSet = computed(() => new Set(systemStore.sources.disabled || []))
const selectedPaths = ref(new Set())
const bulkWorking = ref(false)

const isEnabled = (path) => !disabledSet.value.has(path)

const sourcesList = computed(() => {
  const build = (type, dir) => (systemStore.sources[type] || []).map(name => ({
    name,
    type,
    path: `${dir}/${name}`,
    enabled: !disabledSet.value.has(`${dir}/${name}`)
  }))
  return [
    ...build('js', 'spider/js'),
    ...build('dr2', 'spider/js_dr2'),
    ...build('catvod', 'spider/catvod'),
    ...build('php', 'spider/php'),
    ...build('py', 'spider/py'),
  ]
})

// 批量操作
const allSelected = computed(() =>
  filteredSources.value.length > 0 && filteredSources.value.every(s => selectedPaths.value.has(s.path))
)
const toggleSelect = (source) => {
  const next = new Set(selectedPaths.value)
  next.has(source.path) ? next.delete(source.path) : next.add(source.path)
  selectedPaths.value = next
}
const toggleSelectAll = () => {
  selectedPaths.value = allSelected.value
    ? new Set()
    : new Set(filteredSources.value.map(s => s.path))
}

const refreshAfterToggle = async () => {
  await systemStore.fetchSources() // 刷新列表（含 disabled 清单）
  // 清掉已不在列表中的勾选
  const alive = new Set(sourcesList.value.map(s => s.path))
  selectedPaths.value = new Set([...selectedPaths.value].filter(p => alive.has(p)))
}

const applyEnabled = async (paths, enabled, confirmHint) => {
  if (!enabled && confirmHint) {
    const ok = await dialog.confirm({
      title: `停用 ${paths.length} 个源`,
      message: '停用后配置列表与订阅分发将不再包含这些源（全源检测仍会覆盖它们）。',
      confirmText: '停用',
    })
    if (!ok) return false
  }
  bulkWorking.value = true
  try {
    const res = await adminApi.setSourcesEnabled(paths, enabled)
    if (res && res.success) {
      toast.success(`${enabled ? '启用' : '停用'}了 ${res.data.updated} 个源`)
      await refreshAfterToggle()
      return true
    }
    toast.error('操作失败: ' + (res?.error || '未知错误'))
    return false
  } catch (error) {
    toast.error('操作失败: ' + (error?.response?.data?.error || error.message))
    return false
  } finally {
    bulkWorking.value = false
  }
}

const toggleSource = async (source, targetEnabled) => {
  // USwitch 的 update 事件传入目标状态（true=启用）
  await applyEnabled([source.path], targetEnabled ?? !source.enabled, false)
}

const bulkSetEnabled = async (enabled) => {
  const paths = [...selectedPaths.value]
  if (!paths.length) return
  const ok = await applyEnabled(paths, enabled, !enabled)
  if (ok) selectedPaths.value = new Set()
}

const filterType = ref('all')
const searchQuery = ref('')
const onlyDisabled = ref(false)

const TYPE_FILTERS = [
  { value: 'all', label: '全部' },
  { value: 'js', label: 'JS' },
  { value: 'dr2', label: 'DR2' },
  { value: 'catvod', label: 'CatVod' },
  { value: 'php', label: 'PHP' },
  { value: 'py', label: 'HIPY' },
]

const TYPE_TONES = { js: 'info', dr2: 'neutral', catvod: 'warn', php: 'accent', py: 'ok' }

const ENGINE_OPTIONS = [
  { label: 'JS 源（spider/js）', value: 'js' },
  { label: 'DR2 源（spider/js_dr2）', value: 'dr2' },
  { label: 'CatVod 源（spider/catvod）', value: 'catvod' },
  { label: 'PHP 源（spider/php）', value: 'php' },
  { label: 'HIPY 源（spider/py）', value: 'py' },
]

const ENGINE_EXT = { js: '.js', dr2: '.js', catvod: '.js', php: '.php', py: '.py' }

const filteredSources = computed(() => {
  return sourcesList.value.filter(source => {
    const matchesType = filterType.value === 'all' || source.type === filterType.value
    const matchesSearch = !searchQuery.value ||
      source.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesDisabled = !onlyDisabled.value || !source.enabled
    return matchesType && matchesSearch && matchesDisabled
  })
})

const disabledCount = computed(() => sourcesList.value.filter(s => !s.enabled).length)

const stats = computed(() => [
  { label: '显示', value: filteredSources.value.length },
  { label: '已停用', value: disabledCount.value },
  { label: 'JS 源', value: systemStore.sources.js?.length || 0 },
  { label: 'DR2 源', value: systemStore.sources.dr2?.length || 0 },
  { label: 'CatVod', value: systemStore.sources.catvod?.length || 0 },
  { label: 'PHP 源', value: systemStore.sources.php?.length || 0 },
  { label: 'HIPY 源', value: systemStore.sources.py?.length || 0 },
])

onMounted(() => {
  systemStore.fetchSources()
})

const validateSource = async (source) => {
  validating.value = source.path
  validationResults.value[source.path] = null

  try {
    const result = await spiderApi.validateSpider(source.path)
    validationResults.value[source.path] = {
      kind: 'syntax',
      success: !result.isError,
      message: result.content?.[0]?.text || '验证完成'
    }
  } catch (e) {
    validationResults.value[source.path] = {
      kind: 'syntax',
      success: false,
      message: e.message
    }
  } finally {
    validating.value = null
  }
}

// 接口全流程验证：服务端自调用 首页→分类→详情→搜索（→可选播放）
const runFlowVerify = async (source) => {
  flowVerifying.value = source.path
  validationResults.value[source.path] = { kind: 'flow', running: true, steps: [], verdict: null }
  try {
    const res = await adminApi.verifySourceFlow(source.path, {
      searchKeyword: '爱',
      perStepTimeoutMs: 10000,
      verifyPlay: flowVerifyPlay.value,
    })
    if (res && res.success) {
      validationResults.value[source.path] = { kind: 'flow', running: false, ...res.data }
    } else {
      validationResults.value[source.path] = { kind: 'syntax', success: false, message: res?.error || '流程验证失败' }
    }
  } catch (error) {
    validationResults.value[source.path] = { kind: 'syntax', success: false, message: error?.response?.data?.error || error.message }
  } finally {
    flowVerifying.value = null
  }
}

const editSource = (source) => {
  router.push({
    name: 'source-editor',
    query: { path: source.path }
  })
}

// ==================== 上传源 ====================
const showUpload = ref(false)
const uploadEngine = ref('js')
const uploadFile = ref(null) // File 对象
const uploadContent = ref('')
const uploadBusy = ref(false)
const uploadError = ref('')
const fileInput = ref(null)

// 同名检测：选中文件后即时探测目标是否存在（存在则前端预置 overwrite，提交时提示确认）
const willOverwrite = computed(() => {
  if (!uploadFile.value) return false
  return (systemStore.sources[uploadEngine.value] || []).includes(uploadFile.value.name)
})

const acceptTypes = computed(() => ENGINE_EXT[uploadEngine.value] || '.js')

const pickFile = () => fileInput.value?.click()

const onFileChosen = async (e) => {
  uploadError.value = ''
  const file = e.target.files?.[0]
  e.target.value = '' // 允许重复选择同一文件
  if (!file) return
  if (!file.name.endsWith(acceptTypes.value)) {
    uploadError.value = `文件扩展名需为 ${acceptTypes.value}`
    uploadFile.value = null
    uploadContent.value = ''
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    uploadError.value = '文件超过 5MB 上限'
    uploadFile.value = null
    uploadContent.value = ''
    return
  }
  try {
    uploadContent.value = await file.text()
    uploadFile.value = file
  } catch (err) {
    uploadError.value = '读取文件失败: ' + err.message
  }
}

const openUpload = () => {
  uploadEngine.value = filterType.value !== 'all' ? filterType.value : 'js'
  uploadFile.value = null
  uploadContent.value = ''
  uploadError.value = ''
  showUpload.value = true
}

const submitUpload = async () => {
  if (!uploadFile.value || !uploadContent.value) return
  if (willOverwrite.value) {
    const ok = await dialog.confirm({
      title: '覆盖现有源',
      message: `源「${uploadFile.value.name}」已存在于 ${uploadEngine.value} 引擎目录，上传将覆盖其内容。`,
      confirmText: '覆盖上传',
    })
    if (!ok) return
  }
  uploadBusy.value = true
  uploadError.value = ''
  try {
    const res = await adminApi.uploadSource(uploadEngine.value, uploadFile.value.name, uploadContent.value, true)
    if (res && res.success) {
      const check = res.data?.check
      if (check && check.ok === false) {
        toast.warning(`已上传，但语法校验未通过：${check.message || '未知原因'}`)
      } else {
        toast.success(`源 ${uploadFile.value.name} 已上传`)
      }
      showUpload.value = false
      await systemStore.fetchSources()
    } else {
      uploadError.value = res?.error || '上传失败'
    }
  } catch (error) {
    const code = error?.response?.status
    if (code === 409) {
      uploadError.value = '同名源已存在，请确认后覆盖上传'
    } else {
      uploadError.value = error?.response?.data?.error || error.message
    }
  } finally {
    uploadBusy.value = false
  }
}

// ==================== 删除源 ====================
const deletingPath = ref('')

const deleteSource = async (source) => {
  const ok = await dialog.confirm({
    title: `删除源 ${source.name}`,
    message: `将从 ${source.path} 删除该源文件，操作不可恢复。`,
    danger: true,
    confirmText: '删除',
    requireText: source.name,
  })
  if (!ok) return
  deletingPath.value = source.path
  try {
    const res = await adminApi.deleteSource(source.path)
    if (res && res.success) {
      toast.success(`源 ${source.name} 已删除`)
      delete validationResults.value[source.path]
      await systemStore.fetchSources()
    } else {
      toast.error('删除失败: ' + (res?.error || '未知错误'))
    }
  } catch (error) {
    toast.error('删除失败: ' + (error?.response?.data?.error || error.message))
  } finally {
    deletingPath.value = ''
  }
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// SOURCES" title="源管理" desc="管理和验证 drpy 源文件">
      <template #actions>
        <div class="relative w-full sm:w-52">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-low" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input v-model="searchQuery" type="text" class="input h-9 pl-9 pr-8" placeholder="搜索源文件…">
          <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
        </div>
        <UButton size="sm" :loading="systemStore.loading" @click="systemStore.fetchSources()">
          <svg v-if="!systemStore.loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          刷新列表
        </UButton>
        <UButton size="sm" variant="primary" @click="openUpload">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
          上传源
        </UButton>
      </template>
    </PageHeader>

    <!-- 类型筛选 chips -->
    <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar pb-3 shrink-0">
      <button
        v-for="t in TYPE_FILTERS" :key="t.value"
        class="px-3 h-8 rounded-full text-xs whitespace-nowrap transition-colors border shrink-0"
        :class="filterType === t.value
          ? 'bg-accent-dim text-accent border-accent/40'
          : 'text-mid border-line hover:text-hi hover:border-mid/40'"
        @click="filterType = t.value"
      >{{ t.label }}</button>
      <button
        class="px-3 h-8 rounded-full text-xs whitespace-nowrap transition-colors border shrink-0"
        :class="onlyDisabled
          ? 'bg-warn/15 text-warn border-warn/40'
          : 'text-mid border-line hover:text-warn hover:border-warn/40'"
        @click="onlyDisabled = !onlyDisabled"
      >已停用 ({{ disabledCount }})</button>
    </div>

    <!-- 批量操作工具行（勾选非空时出现） -->
    <div v-if="selectedPaths.size > 0" class="mb-3 px-3 py-2 bg-accent-dim border border-accent/30 rounded-ctrl flex flex-wrap items-center gap-2 shrink-0">
      <span class="text-xs text-accent">已选 {{ selectedPaths.size }} 个源</span>
      <div class="flex-1" />
      <UButton size="sm" :disabled="bulkWorking" @click="bulkSetEnabled(true)">批量启用</UButton>
      <UButton size="sm" variant="danger" :loading="bulkWorking" @click="bulkSetEnabled(false)">批量停用</UButton>
      <UButton size="sm" variant="ghost" @click="selectedPaths = new Set()">取消选择</UButton>
    </div>

    <div class="flex-1 overflow-y-auto min-h-0 space-y-3">
      <!-- 统计条 -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 enter-stagger">
        <StatCard v-for="(s, i) in stats" :key="s.label" :label="s.label" :value="s.value" :style="{ '--i': Math.min(i, 5) }" />
      </div>

      <!-- Sources list -->
      <div class="panel overflow-hidden" style="--i: 2">
        <div v-if="systemStore.loading && sourcesList.length === 0" class="p-4 space-y-2">
          <USkeleton v-for="i in 6" :key="i" />
        </div>

        <UEmpty v-else-if="filteredSources.length === 0" text="没有找到匹配的源文件" />

        <div v-else>
          <!-- 全选行 -->
          <label class="flex items-center gap-2.5 px-4 py-2 border-b border-line/70 cursor-pointer select-none hover:bg-mid/[0.03]">
            <input
              type="checkbox"
              class="w-4 h-4 accent-current shrink-0"
              :checked="allSelected"
              @change="toggleSelectAll"
            >
            <span class="text-2xs text-low">全选（当前筛选结果 {{ filteredSources.length }} 项）</span>
          </label>

          <div class="divide-y divide-line/70">
          <div
            v-for="source in filteredSources"
            :key="source.path"
            class="px-4 py-3 hover:bg-mid/[0.03] transition-colors group"
            :class="{ 'opacity-60': !source.enabled }"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-start gap-2.5 flex-1 min-w-0">
                <input
                  type="checkbox"
                  class="w-4 h-4 mt-0.5 accent-current shrink-0"
                  :checked="selectedPaths.has(source.path)"
                  @change="toggleSelect(source)"
                >
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <UBadge :tone="TYPE_TONES[source.type]">{{ source.type === 'py' ? 'HIPY' : source.type.toUpperCase() }}</UBadge>
                    <h4 class="text-[13px] font-medium text-hi truncate">{{ source.name }}</h4>
                    <UBadge v-if="!source.enabled" tone="warn">已停用</UBadge>
                  </div>
                  <p class="text-2xs text-low font-mono mt-0.5 truncate">{{ source.path }}</p>

                  <p
                    v-if="validationResults[source.path] && validationResults[source.path].kind !== 'flow'"
                    class="mt-1.5 text-xs"
                    :class="validationResults[source.path].success ? 'text-ok' : 'text-danger'"
                  >{{ validationResults[source.path].message }}</p>
                </div>
              </div>

              <div class="flex items-center gap-1 shrink-0">
                <USwitch
                  :model-value="source.enabled"
                  :title="source.enabled ? '点击停用' : '点击启用'"
                  @update:model-value="v => toggleSource(source, v)"
                />
                <UButton size="sm" variant="ghost" :loading="validating === source.path" title="语法检查" @click="validateSource(source)">
                  <svg v-if="validating !== source.path" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </UButton>
                <UButton size="sm" variant="ghost" :loading="flowVerifying === source.path" title="接口全流程验证（首页/分类/详情/搜索）" @click="runFlowVerify(source)">
                  <svg v-if="flowVerifying !== source.path" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><circle cx="12" cy="12" r="9" stroke-width="2" /></svg>
                </UButton>
                <UButton size="sm" variant="ghost" title="编辑源" @click="editSource(source)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                </UButton>
                <UButton size="sm" variant="ghost" class="hover:!text-danger" :loading="deletingPath === source.path" title="删除源" @click="deleteSource(source)">
                  <svg v-if="deletingPath !== source.path" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </UButton>
              </div>
            </div>

            <!-- 接口全流程验证：步骤面板 -->
            <div v-if="validationResults[source.path]?.kind === 'flow'" class="mt-3 pt-3 border-t border-line/70 space-y-2">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <span class="eyebrow">// 接口流程验证</span>
                  <UBadge v-if="validationResults[source.path].verdict" :tone="VERDICT_META[validationResults[source.path].verdict]?.tone || 'neutral'">
                    {{ VERDICT_META[validationResults[source.path].verdict]?.label || validationResults[source.path].verdict }}
                    （{{ validationResults[source.path].okSteps }}/{{ validationResults[source.path].totalSteps }}）
                  </UBadge>
                </div>
                <span v-if="validationResults[source.path].costMs" class="text-2xs font-mono text-low">总耗时 {{ validationResults[source.path].costMs }}ms</span>
              </div>

              <div class="space-y-1">
                <div
                  v-for="step in validationResults[source.path].steps"
                  :key="step.step"
                  class="flex items-center gap-2 text-xs px-2 py-1 rounded bg-sunken"
                >
                  <span
                    class="w-1.5 h-1.5 rounded-full shrink-0"
                    :class="step.skipped ? 'bg-low/40' : step.ok ? 'bg-ok' : 'bg-danger'"
                  />
                  <span class="text-mid w-10 shrink-0">{{ step.label }}</span>
                  <span v-if="step.skipped" class="text-low">已跳过（{{ step.error || '前序步骤未通过' }}）</span>
                  <template v-else>
                    <span :class="step.ok ? 'text-ok' : 'text-danger'">{{ step.ok ? '正常' : (step.error || '失败') }}</span>
                    <span v-if="step.costMs != null" class="text-low font-mono">{{ step.costMs }}ms</span>
                    <span v-if="step.items" class="text-low font-mono">{{ step.items }} 项</span>
                    <span v-if="step.playable" class="text-ok">可播放</span>
                    <span v-if="step.step === 'detail' && !step.playable && step.ok" class="text-warn">无播放地址</span>
                  </template>
                </div>
              </div>

              <label class="flex items-center gap-2 text-2xs text-low cursor-pointer select-none pt-0.5">
                <input v-model="flowVerifyPlay" type="checkbox" class="w-3.5 h-3.5 accent-current">
                深度验证播放（外站地址有效性不可控，不计入评分）
              </label>
              <UButton size="sm" variant="soft" :loading="flowVerifying === source.path" @click="runFlowVerify(source)">重新验证</UButton>
            </div>
          </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ 上传源弹窗 ============ -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showUpload" class="fixed inset-0 z-50 flex sm:items-center items-end justify-center sm:p-4 bg-black/60 backdrop-blur-[2px]" @click.self="showUpload = false">
          <div class="bg-panel border border-line w-full sm:max-w-sm flex flex-col max-h-[90dvh] rounded-t-panel sm:rounded-panel" :style="{ boxShadow: 'var(--shadow-float)' }">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between shrink-0">
              <h3 class="text-sm font-semibold text-hi">上传源</h3>
              <button class="p-1.5 -mr-1.5 text-low hover:text-hi transition-colors" @click="showUpload = false">✕</button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3.5">
              <div>
                <label class="block text-xs text-mid mb-1">源类型</label>
                <USelect v-model="uploadEngine" :options="ENGINE_OPTIONS" />
              </div>

              <div>
                <label class="block text-xs text-mid mb-1">源文件 <span class="text-danger">*</span></label>
                <input ref="fileInput" type="file" :accept="acceptTypes" class="hidden" @change="onFileChosen">
                <button
                  class="w-full h-16 rounded-ctrl border border-dashed border-line hover:border-accent/50 hover:bg-accent/[0.04] transition-colors flex flex-col items-center justify-center gap-1"
                  @click="pickFile"
                >
                  <svg class="w-4 h-4 text-low" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                  <span v-if="uploadFile" class="text-xs text-hi font-mono truncate max-w-full px-3">{{ uploadFile.name }}</span>
                  <span v-else class="text-xs text-low">点击选择 {{ acceptTypes }} 文件</span>
                </button>
                <p v-if="willOverwrite" class="mt-1.5 text-2xs text-warn">⚠ 同名源已存在，上传将覆盖其内容（提交时需确认）</p>
              </div>

              <p v-if="uploadError" class="text-xs text-danger break-all">{{ uploadError }}</p>
            </div>

            <div class="px-4 py-3 pb-safe border-t border-line flex justify-end gap-2 shrink-0 sm:pb-3">
              <UButton size="sm" class="flex-1 sm:flex-none" @click="showUpload = false">取消</UButton>
              <UButton size="sm" variant="primary" class="flex-1 sm:flex-none" :loading="uploadBusy" :disabled="!uploadFile" @click="submitUpload">上传</UButton>
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

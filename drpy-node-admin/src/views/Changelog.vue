<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { adminApi } from '../api/admin'
import { useToast } from '../composables/useToast'

const toast = useToast()

const loading = ref(false)
const releases = ref([])
const archive = ref({ items: [] })
const latest = ref(null)

const searchQuery = ref('')
const typeFilter = ref('all')
const tagFilter = ref([])
const visibleCount = ref(8)
const showArchive = ref(false)
const expandedArchived = ref(false)
const searchInput = ref(null)

// 条目类型：中文徽章文案 + 语义色（与 docs/changelog-design.md §4.4 一致）
const ITEM_TYPES = [
  { key: 'feat', label: '新功能', tone: 'accent', text: 'text-accent' },
  { key: 'fix', label: '修复', tone: 'warn', text: 'text-warn' },
  { key: 'perf', label: '优化', tone: 'info', text: 'text-info' },
  { key: 'refactor', label: '重构', tone: 'neutral', text: 'text-mid' },
  { key: 'security', label: '安全', tone: 'danger', text: 'text-danger' },
  { key: 'docs', label: '文档', tone: 'neutral', text: 'text-low' },
  { key: 'other', label: '其他', tone: 'neutral', text: 'text-low' },
]
const typeMeta = (key) => ITEM_TYPES.find(t => t.key === key) || ITEM_TYPES[ITEM_TYPES.length - 1]

// 版本卡片左缘色条取「最高优先级条目」的颜色
// （类名必须静态写全，Tailwind JIT 不编译动态拼接）
const TYPE_PRIORITY = ['security', 'feat', 'fix', 'perf', 'refactor', 'docs', 'other']
const BAR_CLASSES = {
  security: 'border-l-danger',
  feat: 'border-l-accent',
  fix: 'border-l-warn',
  perf: 'border-l-info',
  refactor: 'border-l-mid',
  docs: 'border-l-low',
  other: 'border-l-low',
}
const barClass = (release) => {
  const top = TYPE_PRIORITY.find(p => release.items.some(i => i.type === p))
  return BAR_CLASSES[top] || BAR_CLASSES.other
}

onMounted(async () => {
  window.addEventListener('keydown', onGlobalKey)
  await load()
})
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKey))

const onGlobalKey = (e) => {
  if (e.key === '/' && document.activeElement?.tagName !== 'INPUT') {
    e.preventDefault()
    searchInput.value?.focus()
  }
  if (e.key === 'Escape' && document.activeElement === searchInput.value) {
    searchQuery.value = ''
    searchInput.value?.blur()
  }
}

const load = async () => {
  loading.value = true
  try {
    const result = await adminApi.getChangelog()
    const data = result?.data || {}
    releases.value = data.releases || []
    archive.value = data.archive || { items: [] }
    latest.value = data.latest || null
  } catch (e) {
    console.error('加载更新日志失败:', e)
    toast.error('加载更新日志失败: ' + e.message)
  } finally {
    loading.value = false
  }
}

const debounce = (fn, ms) => {
  let t
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms) }
}

// ---------- 过滤 ----------

const allTags = computed(() => {
  const set = new Set()
  releases.value.forEach(r => (r.tags || []).forEach(t => set.add(t)))
  return [...set]
})

const TYPE_CHIPS = [{ key: 'all', label: '全部' }, ...ITEM_TYPES.map(t => ({ key: t.key, label: t.label }))]

const matchesQuery = (text, q) => text.toLowerCase().includes(q.toLowerCase())

const filteredReleases = computed(() => {
  const q = searchQuery.value.trim()
  if (!q && typeFilter.value === 'all' && tagFilter.value.length === 0) return releases.value

  const normQ = q.replace(/^v/i, '')
  const result = []
  for (const r of releases.value) {
    const versionHit = q && (matchesQuery(r.version, normQ) || matchesQuery(r.version, q))
    const titleHit = q && matchesQuery(r.title || '', q)
    const tagHitList = (r.tags || []).filter(t => q && matchesQuery(t, q))
    const hitItems = r.items.filter(i =>
      (!q || matchesQuery(i.text, q)) &&
      (typeFilter.value === 'all' || i.type === typeFilter.value)
    )
    const tagFiltered = tagFilter.value.length === 0 || tagFilter.value.every(t => (r.tags || []).includes(t))
    if (!tagFiltered) continue

    // 搜索或类型过滤命中：版本号/标题/标签任一命中（类型过滤时必须有对应条目）
    const fieldHit = q && (versionHit || titleHit || tagHitList.length > 0)
    if (q && !fieldHit && hitItems.length === 0) continue
    if (!q && typeFilter.value !== 'all' && hitItems.length === 0) continue

    // 版本级命中（版本号/标题/标签）时保留全部条目，否则只展示类型过滤后的条目
    result.push({
      ...r,
      items: q && !versionHit && !titleHit && !(tagHitList.length > 0) ? hitItems : r.items,
      _hit: Boolean(q),
    })
  }
  return result
})

// ---------- 高亮（分段渲染，不用 v-html） ----------

const splitHighlight = (text, q) => {
  if (!q) return [{ text, hit: false }]
  const parts = []
  const lower = text.toLowerCase()
  const lowerQ = q.toLowerCase()
  let pos = 0
  while (pos < text.length) {
    const idx = lower.indexOf(lowerQ, pos)
    if (idx === -1) { parts.push({ text: text.slice(pos), hit: false }); break }
    if (idx > pos) parts.push({ text: text.slice(pos, idx), hit: false })
    parts.push({ text: text.slice(idx, idx + q.length), hit: true })
    pos = idx + q.length
  }
  return parts
}

const hl = (text) => splitHighlight(text, searchQuery.value.trim())

const isCollapsed = computed(() =>
  !searchQuery.value.trim() && typeFilter.value === 'all' && tagFilter.value.length === 0
)

const visibleReleases = computed(() =>
  isCollapsed.value ? filteredReleases.value.slice(0, visibleCount.value) : filteredReleases.value
)

const toggleTag = (tag) => {
  const idx = tagFilter.value.indexOf(tag)
  if (idx >= 0) tagFilter.value.splice(idx, 1)
  else tagFilter.value.push(tag)
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// CHANGELOG" title="更新日志" desc="drpyS 各版本变更记录">
      <template #actions>
        <div class="relative w-full sm:w-56">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-low" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input ref="searchInput" v-model="searchQuery" type="text" class="input h-9 pl-9 pr-8" placeholder="搜索版本 / 内容…">
          <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
        </div>
      </template>
    </PageHeader>

    <!-- 头部概览 -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3 shrink-0">
      <StatCard label="当前版本" :value="latest ? 'v' + latest : '—'" tone="accent" dot="running" />
      <StatCard label="版本总数" :value="releases.length" />
      <StatCard label="最近发布" :value="releases[0]?.date || '—'" />
    </div>

    <!-- 过滤行 -->
    <div class="flex items-center gap-1.5 flex-wrap mb-3 shrink-0">
      <button
        v-for="chip in TYPE_CHIPS"
        :key="chip.key"
        class="px-3 min-h-[36px] rounded-ctrl text-xs border transition-colors"
        :class="typeFilter === chip.key ? 'border-accent/60 bg-accent-dim text-accent' : 'border-line text-mid hover:text-hi'"
        @click="typeFilter = chip.key"
      >{{ chip.label }}</button>
      <span v-if="allTags.length" class="w-px h-4 bg-line mx-1" />
      <button
        v-for="tag in allTags"
        :key="tag"
        class="px-2.5 min-h-[36px] rounded-full text-2xs font-mono border transition-colors"
        :class="tagFilter.includes(tag) ? 'border-accent/60 bg-accent-dim text-accent' : 'border-line text-low hover:text-hi'"
        @click="toggleTag(tag)"
      >{{ tag }}</button>
    </div>

    <!-- 时间轴 -->
    <div class="flex-1 overflow-y-auto min-h-0">
      <div v-if="loading" class="space-y-3">
        <USkeleton v-for="i in 4" :key="i" />
      </div>

      <UEmpty v-else-if="visibleReleases.length === 0" icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" text="没有匹配的更新记录" />

      <div v-else class="max-w-4xl pl-4 sm:pl-6 border-l border-line space-y-8" style="margin-left: 8px">
        <div v-for="(release, ri) in visibleReleases" :key="release.version" class="relative enter-stagger" :style="{ '--i': Math.min(ri, 8) }">
          <!-- 轨道节点 -->
          <span
            class="absolute -left-[26px] sm:-left-[30px] top-1.5 rounded-full"
            :class="[
              release.type === 'major' ? 'w-3 h-3 bg-accent ring-4 ring-accent/15' :
              release.type === 'minor' ? 'w-2.5 h-2.5 border-2 border-accent' : 'w-2 h-2 bg-mid/40',
              ri === 0 && isCollapsed ? 'animate-dot-breath' : ''
            ]"
          />
          <!-- 版本头 -->
          <div class="flex items-baseline gap-2.5 flex-wrap">
            <span class="font-mono text-lg text-hi tracking-tight">v{{ release.version }}</span>
            <span class="text-2xs text-low font-mono">{{ release.date }}</span>
            <UBadge v-if="release.type === 'major'" tone="accent" class="!py-0">MAJOR</UBadge>
            <span v-if="ri === 0 && isCollapsed" class="text-2xs text-accent font-mono">● 最新</span>
          </div>
          <!-- 版本卡片 -->
          <div class="panel mt-2 border-l-[3px] overflow-hidden transition-colors hover:border-accent/30" :class="barClass(release)">
            <div class="px-4 py-2.5 border-b border-line">
              <h3 class="text-sm font-medium text-hi">{{ release.title }}</h3>
              <div v-if="release.tags?.length" class="flex items-center gap-1.5 flex-wrap mt-1.5">
                <button
                  v-for="tag in release.tags"
                  :key="tag"
                  class="px-2 py-0.5 rounded-full text-2xs font-mono border transition-colors"
                  :class="tagFilter.includes(tag) ? 'border-accent/60 bg-accent-dim text-accent' : 'border-line text-low hover:text-accent'"
                  @click="toggleTag(tag)"
                >#{{ tag }}</button>
              </div>
            </div>
            <div class="divide-y divide-line/50">
              <div v-for="(item, ii) in release.items" :key="ii" class="px-4 py-2.5 flex items-start gap-2.5">
                <UBadge :tone="typeMeta(item.type).tone" class="shrink-0 mt-0.5">{{ item.label || typeMeta(item.type).label }}</UBadge>
                <p class="text-[13px] text-mid leading-relaxed min-w-0">
                  <template v-for="(seg, si) in hl(item.text)" :key="si">
                    <mark v-if="seg.hit" class="bg-accent-dim text-accent rounded-[2px] px-0.5">{{ seg.text }}</mark>
                    <template v-else>{{ seg.text }}</template>
                  </template>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- 展开更早版本 -->
        <button
          v-if="isCollapsed && filteredReleases.length > visibleCount"
          class="w-full panel py-3 text-xs text-mid hover:text-accent transition-colors"
          @click="visibleCount = releases.length"
        >展开更早版本（{{ filteredReleases.length - visibleCount }}）</button>

        <!-- 历史归档 -->
        <div v-if="archive.items?.length && isCollapsed" class="panel">
          <button class="w-full px-4 py-3 flex items-center justify-between hover:bg-mid/[0.03] transition-colors" @click="expandedArchived = !expandedArchived">
            <h3 class="text-[13px] font-semibold text-hi flex items-center gap-2">
              <span class="w-1 h-3.5 bg-low rounded-full" />
              历史归档
            </h3>
            <span class="font-mono text-2xs text-low">{{ archive.items.length }} 条早期记录</span>
          </button>
          <div v-if="expandedArchived" class="border-t border-line divide-y divide-line/50 max-h-72 overflow-y-auto">
            <p v-for="(item, ii) in archive.items" :key="ii" class="px-4 py-2 text-xs text-low">{{ item.text }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

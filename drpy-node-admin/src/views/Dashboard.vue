<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useSystemStore } from '../stores/system'
import { navGroups } from '../navigation'
import apiClient from '../api/client'
import { useToast } from '../composables/useToast'
import { useDialog } from '../composables/useDialog'

const systemStore = useSystemStore()
const toast = useToast()
const dialog = useDialog()
const healthCheckInterval = ref(null)
const isTerminalAvailable = ref(false)

onMounted(async () => {
  await systemStore.checkHealth()
  await systemStore.fetchRoutes()
  await systemStore.fetchSources()

  // Check terminal status
  try {
    const res = await apiClient.get('/api/admin/terminal/status')
    if (res && res.available) {
      isTerminalAvailable.value = true
    }
  } catch (error) {
    console.error('Failed to get terminal status:', error)
  }

  // Check health every 30 seconds
  healthCheckInterval.value = setInterval(() => {
    systemStore.checkHealth()
  }, 30000)
})

onUnmounted(() => {
  if (healthCheckInterval.value) {
    clearInterval(healthCheckInterval.value)
  }
})

const healthy = computed(() => ['ok', 'healthy'].includes(systemStore.health.status))
const healthTone = computed(() => healthy.value ? 'ok' : systemStore.health.status ? 'error' : 'warn')

const restarting = ref(false)

const formatUptime = (seconds) => {
  if (!seconds) return '0秒';
  const d = Math.floor(seconds / (3600 * 24));
  const h = Math.floor((seconds % (3600 * 24)) / 3600);
  const m = Math.floor((seconds % 3600) / 60);

  let result = '';
  if (d > 0) result += `${d}天`;
  if (h > 0 || d > 0) result += `${h}时`;
  result += `${m}分`;

  return result;
}

const restartService = async () => {
  const ok = await dialog.confirm({
    title: '重启 drpy-node 服务',
    message: '重启将中断所有进行中的连接与请求，确定继续吗？',
    danger: true,
    confirmText: '重启服务',
  })
  if (!ok) return

  restarting.value = true
  try {
    const result = await systemStore.restartService()

    let message = '操作已完成'

    if (result && typeof result === 'object') {
      if (result.message) {
        message = result.message
      } else if (result.error) {
        message = '操作失败: ' + result.error
      } else if (result.content && result.content[0] && result.content[0].text) {
        // 兼容旧版 MCP 响应格式
        try {
          const parsed = JSON.parse(result.content[0].text)
          message = parsed.message || message
        } catch {
          // ignore
        }
      }
    } else if (typeof result === 'string') {
      try {
        const parsed = JSON.parse(result)
        message = parsed.message || result
      } catch {
        message = result
      }
    }

    toast.info(message)
  } catch (e) {
    toast.error('重启失败: ' + e.message)
  } finally {
    restarting.value = false
  }
}

const stats = computed(() => {
  const s = systemStore.sources
  return [
    { label: 'JS 源', value: s.js?.length || 0, icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { label: 'CatVod', value: s.catvod?.length || 0, icon: 'M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z' },
    { label: 'PHP 源', value: s.php?.length || 0, icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
    { label: 'PY 源', value: s.py?.length || 0, icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { label: '总源数', value: (s.js?.length || 0) + (s.catvod?.length || 0) + (s.php?.length || 0) + (s.py?.length || 0), icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
    { label: '路由数', value: systemStore.routes.registered_controllers?.length || 0, icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
  ]
})

// 快捷操作直接从侧边栏导航数据派生（单一数据源）：
// 顺序、图标、终端可用性过滤与侧边栏完全一致，仅排除仪表盘自身
const quickLinks = computed(() => {
  return navGroups
    .flatMap(g => g.items)
    .filter(item => item.path !== '/' && (!item.needTerminal || isTerminalAvailable.value))
    .map(item => ({ to: item.path, label: item.label, icon: item.icon }))
})</script>

<template>
  <div class="h-full flex flex-col min-h-0 enter-stagger">
    <PageHeader eyebrow="// DASHBOARD" title="仪表盘">
      <template #actions>
        <UButton size="sm" :loading="restarting" @click="restartService">
          <svg v-if="!restarting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          重启服务
        </UButton>
      </template>
    </PageHeader>

    <div class="flex-1 overflow-y-auto min-h-0 space-y-4">
      <!-- 服务状态面板 -->
      <div class="panel p-5" style="--i: 0">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <div class="flex items-center gap-2.5">
            <StatusDot :tone="healthTone" />
            <h2 class="text-sm font-semibold text-hi">服务状态</h2>
            <UBadge :tone="healthTone === 'ok' ? 'ok' : healthTone === 'error' ? 'danger' : 'warn'" class="font-mono uppercase">
              {{ systemStore.health.status?.toUpperCase() || 'UNKNOWN' }}
            </UBadge>
          </div>
          <span v-if="systemStore.health.uptime" class="font-mono text-2xs text-low">
            UPTIME {{ formatUptime(systemStore.health.uptime) }}
          </span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-[13px]">
          <div class="flex items-center justify-between gap-3">
            <span class="text-low">运行环境</span>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              <UBadge>{{ systemStore.health.platform?.platform }}</UBadge>
              <UBadge>{{ systemStore.health.platform?.arch }}</UBadge>
              <UBadge tone="accent">Node {{ systemStore.health.platform?.nodeVersion }}</UBadge>
            </div>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-low">运行组件</span>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              <UBadge :tone="systemStore.health.env?.php ? 'ok' : 'neutral'" title="PHP 环境状态">PHP {{ typeof systemStore.health.env?.php === 'string' ? systemStore.health.env.php : (systemStore.health.env?.php ? 'ON' : 'OFF') }}</UBadge>
              <UBadge :tone="systemStore.health.env?.python ? 'info' : 'neutral'" title="Python 环境状态">PY {{ typeof systemStore.health.env?.python === 'string' ? systemStore.health.env.python : (systemStore.health.env?.python ? 'ON' : 'OFF') }}</UBadge>
              <UBadge tone="accent">v{{ systemStore.health.version || 'Unknown' }}</UBadge>
            </div>
          </div>
        </div>
      </div>

      <!-- 统计卡（mono 数字仪表化） -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <StatCard
          v-for="(s, i) in stats" :key="s.label"
          :label="s.label" :value="s.value" :style="{ '--i': Math.min(i + 1, 8) }"
        />
      </div>

      <!-- 快捷操作 -->
      <div class="panel p-4" style="--i: 2">
        <div class="eyebrow mb-3">// QUICK ACTIONS</div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-8 gap-2">
          <RouterLink
            v-for="link in quickLinks" :key="link.to" :to="link.to"
            class="flex flex-col items-center justify-center gap-2 p-3 rounded-ctrl border border-line text-mid hover:text-accent hover:border-accent/40 hover:bg-accent-dim transition-all min-h-[72px] group"
          >
            <svg class="w-5 h-5 transition-transform group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="link.icon" />
            </svg>
            <span class="text-xs">{{ link.label }}</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LogsView'
}
</script>

<script setup>
import { ref, onMounted, onActivated, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useLogsStore } from '../stores/logs'

const logContainer = ref(null)
const logsStore = useLogsStore()

// Extract reactive state from store
const { wsConnected, wsError, logs, autoScroll } = storeToRefs(logsStore)

const scrollToBottom = () => {
  if (logContainer.value) {
    const lastElement = logContainer.value.lastElementChild
    if (lastElement) {
      lastElement.scrollIntoView({ behavior: 'instant', block: 'end' })
    } else {
      logContainer.value.scrollTop = logContainer.value.scrollHeight
    }
  }
}

const scrollToTop = () => {
  if (logContainer.value) {
    logContainer.value.scrollTop = 0
  }
}

const getLogStyle = (level) => {
  switch (level) {
    case 'error':
    case 'err':
      return 'text-danger'
    case 'warn':
    case 'warning':
      return 'text-warn'
    case 'info':
      return 'text-info'
    case 'debug':
      return 'text-low'
    default:
      return 'text-mid'
  }
}

onMounted(() => {
  if (!logsStore.isInitialized) {
    logsStore.connectWebSocket()
    logsStore.isInitialized = true
  }
})

onActivated(() => {
  if (autoScroll.value) {
    nextTick(() => {
      scrollToBottom()
    })
  }
})

// Watch for auto scroll changes
watch(autoScroll, () => {
  if (autoScroll.value) {
    nextTick(() => {
      scrollToBottom()
    })
  }
})

// Watch logs array to auto-scroll if enabled
watch(() => logs.value.length, () => {
  if (autoScroll.value) {
    nextTick(() => {
      scrollToBottom()
    })
  }
})
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// LOGS" title="日志查看" desc="实时查看应用运行日志">
      <template #actions>
        <span class="inline-flex items-center gap-1.5 px-2.5 h-8 rounded-full border text-xs" :class="wsConnected ? 'border-ok/30 bg-ok/10 text-ok' : 'border-danger/30 bg-danger/10 text-danger'">
          <StatusDot :tone="wsConnected ? 'ok' : 'error'" />
          {{ wsConnected ? '已连接' : '未连接' }}
        </span>
        <UButton size="sm" :variant="wsConnected ? 'secondary' : 'primary'" @click="logsStore.toggleConnection">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          {{ wsConnected ? '断开' : '连接' }}
        </UButton>
        <UButton size="sm" @click="logsStore.clearLogs">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          清空
        </UButton>
      </template>
    </PageHeader>

    <!-- 终端风凹面面板 -->
    <div class="panel flex-1 flex flex-col min-h-0 overflow-hidden" style="--i: 1">
      <!-- 工具栏 -->
      <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-line shrink-0">
        <label class="flex items-center gap-2 text-xs text-mid cursor-pointer select-none">
          <USwitch v-model="autoScroll" />
          自动滚动到底部
        </label>
        <div class="flex items-center gap-2">
          <span class="font-mono text-2xs text-low">{{ logs.length }} 条</span>
          <UButton size="sm" variant="ghost" title="滚动到顶部" @click="scrollToTop">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
          </UButton>
          <UButton size="sm" variant="ghost" title="滚动到底部" @click="scrollToBottom">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
          </UButton>
        </div>
      </div>

      <!-- 日志内容：bg-sunken 凹面 + mono -->
      <div ref="logContainer" class="flex-1 overflow-y-auto overflow-x-hidden px-4 py-3 bg-sunken text-xs font-mono leading-relaxed">
        <template v-if="logs.length === 0">
          <div class="h-full flex items-center justify-center">
            <UEmpty icon="M13 10V3L4 14h7v7l9-11h-7z" :text="!wsConnected ? '点击「连接」按钮开始接收实时日志' : '等待日志…'" />
          </div>
        </template>

        <template v-else>
          <div v-for="(log, index) in logs" :key="index" class="flex gap-3 hover:bg-mid/10 rounded px-2 py-0.5">
            <span class="text-low shrink-0 select-none">{{ log.timestamp }}</span>
            <span class="flex-1 break-words" :class="getLogStyle(log.level)">{{ log.message }}</span>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

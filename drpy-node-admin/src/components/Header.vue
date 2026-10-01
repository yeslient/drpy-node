<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useThemeStore } from '../stores/theme'
import { systemApi } from '../api/system'

const themeStore = useThemeStore()
const route = useRoute()

// 面包屑眉题：路由标题转 mono 大写（如 仪表盘 → // DASHBOARD）
const crumb = computed(() => {
  const title = route.meta.title || 'DRPYS ADMIN'
  const map = {
    '仪表盘': 'DASHBOARD', '环境配置': 'CONFIG', '源管理': 'SOURCES', '源编辑器': 'SOURCE EDITOR',
    '解析管理': 'PARSES', '站源映射': 'MAP MANAGER', '日志查看': 'LOGS', 'API 文档': 'API DOCS',
    '文件管理': 'FILES', '数据库': 'DATABASE', '订阅管理': 'SUBSCRIPTION', '插件管理': 'PLUGINS',
    '插件市场': 'PLUGIN MARKET', '系统备份': 'BACKUP', '终端模拟': 'TERMINAL',
    '加解密工具': 'CRYPTO', 'ZY 加解密': 'ZY CRYPTO',
  }
  return '// ' + (map[title] || String(title).toUpperCase())
})

// 全局服务状态灯：轻量健康检查轮询
const health = ref('unknown') // ok | error | unknown
let timer = null
async function checkHealth() {
  try {
    await systemApi.getHealth()
    health.value = 'ok'
  } catch {
    health.value = 'error'
  }
}
onMounted(() => {
  checkHealth()
  timer = setInterval(checkHealth, 30000)
})
onBeforeUnmount(() => clearInterval(timer))

defineEmits(['open-nav'])
</script>

<template>
  <header class="fixed top-0 right-0 left-0 lg:left-56 z-30 h-14 bg-panel/90 backdrop-blur border-b border-line flex items-center justify-between px-4 lg:px-6">
    <div class="flex items-center gap-3 min-w-0">
      <!-- 移动端：更多导航抽屉 -->
      <button
        class="lg:hidden p-2 -ml-2 rounded-ctrl text-mid hover:text-hi hover:bg-mid/10 transition-colors"
        aria-label="打开导航"
        @click="$emit('open-nav')"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <span class="eyebrow truncate">{{ crumb }}</span>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <!-- 服务状态灯 -->
      <div class="flex items-center gap-1.5 px-2.5 h-7 rounded-full border border-line" :title="health === 'ok' ? '服务在线' : health === 'error' ? '服务异常' : '检测中'">
        <span
          class="inline-block w-1.5 h-1.5 rounded-full"
          :class="{
            'bg-ok animate-dot-breath': health === 'ok',
            'bg-danger animate-dot-error': health === 'error',
            'bg-low': health === 'unknown',
          }"
        />
        <span class="font-mono text-2xs text-low hidden sm:inline">{{ health === 'ok' ? 'ONLINE' : health === 'error' ? 'OFFLINE' : '...' }}</span>
      </div>

      <!-- 主题切换 -->
      <button
        class="p-2 rounded-ctrl text-mid hover:text-hi hover:bg-mid/10 transition-colors"
        :title="themeStore.isDark ? '切换到亮色模式' : '切换到暗色模式'"
        @click="themeStore.toggleTheme"
      >
        <svg v-if="themeStore.isDark" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36l-.7-.7M6.34 6.34l-.7-.7m12.72 0l-.7.7M6.34 17.66l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      </button>
    </div>
  </header>
</template>

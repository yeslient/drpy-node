<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../api/client'
import { useDialog } from '../composables/useDialog'
import NavList from './NavList.vue'

const router = useRouter()
const dialog = useDialog()
const isTerminalAvailable = ref(false)

const goHome = async () => {
  if (await dialog.confirm({ title: '返回主页', message: '确定要离开管理界面返回 drpys 主页吗？' })) {
    window.location.href = '/'
  }
}

onMounted(async () => {
  try {
    const res = await apiClient.get('/api/admin/terminal/status')
    if (res && res.available) {
      isTerminalAvailable.value = true
    }
  } catch (error) {
    console.error('Failed to get terminal status:', error)
  }
})
</script>

<template>
  <!-- PC 固定侧栏 -->
  <aside class="hidden lg:flex fixed inset-y-0 left-0 z-40 w-56 flex-col bg-panel border-r border-line">
    <!-- 品牌区 -->
    <div
      class="flex items-center gap-2.5 h-14 px-4 border-b border-line shrink-0 cursor-pointer hover:bg-mid/5 transition-colors"
      title="返回主页"
      @click="goHome"
    >
      <img src="/drpys.png" alt="Logo" class="w-7 h-7 rounded-full" />
      <div class="min-w-0">
        <div class="font-mono text-sm font-semibold text-hi tracking-wide leading-none">drpyS</div>
        <div class="eyebrow mt-0.5">admin console</div>
      </div>
    </div>

    <!-- 分组导航 -->
    <div class="flex-1 overflow-y-auto">
      <NavList :terminal-available="isTerminalAvailable" />
    </div>

    <!-- 底部固定区 -->
    <div class="border-t border-line px-4 py-3 shrink-0">
      <a
        href="https://github.com/hjdhnx/drpy-node"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center gap-2 text-2xs text-low hover:text-mid transition-colors font-mono"
      >
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
        </svg>
        hjdhnx/drpy-node
      </a>
    </div>
  </aside>
</template>

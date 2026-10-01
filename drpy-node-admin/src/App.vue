<script setup>
import { ref, onMounted } from 'vue'
import { RouterView, RouterLink, useRoute } from 'vue-router'
import { useThemeStore } from './stores/theme'
import { mobileTabs, isActiveNav } from './navigation'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
import NavList from './components/NavList.vue'
import apiClient from './api/client'

const themeStore = useThemeStore()
const route = useRoute()

// 移动端「更多」全量导航抽屉
const navDrawerOpen = ref(false)
const isTerminalAvailable = ref(false)
onMounted(async () => {
  try {
    const res = await apiClient.get('/api/admin/terminal/status')
    if (res && res.available) isTerminalAvailable.value = true
  } catch { /* 探测失败不影响导航 */ }
})
</script>

<template>
  <div class="app-container">
    <!-- PC 侧栏 -->
    <Sidebar />

    <div class="main-content">
      <Header @open-nav="navDrawerOpen = true" />

      <!-- 内容区：图纸点阵底 -->
      <main class="content-area grid-bg">
        <RouterView v-slot="{ Component }">
          <Transition name="fade" mode="out-in">
            <KeepAlive :max="10">
              <component :is="Component" />
            </KeepAlive>
          </Transition>
        </RouterView>
      </main>

      <!-- 移动端底部 Tab -->
      <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-panel/95 backdrop-blur border-t border-line pb-safe">
        <div class="flex items-stretch h-[56px]">
          <template v-for="tab in mobileTabs" :key="tab.label">
            <RouterLink
              v-if="!tab.more"
              :to="tab.path"
              class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] transition-colors"
              :class="isActiveNav(route.path, tab.path) ? 'text-accent' : 'text-low hover:text-mid'"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="tab.icon" />
              </svg>
              <span class="text-[10px]">{{ tab.label }}</span>
            </RouterLink>
            <button
              v-else
              class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] text-low hover:text-mid transition-colors"
              @click="navDrawerOpen = true"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="tab.icon" />
              </svg>
              <span class="text-[10px]">{{ tab.label }}</span>
            </button>
          </template>
        </div>
      </nav>

      <!-- 移动端全量导航抽屉 -->
      <AppDrawer v-model:open="navDrawerOpen" side="left" title="导航">
        <NavList :terminal-available="isTerminalAvailable" @navigate="navDrawerOpen = false" />
        <!-- 抽屉底部：GitHub 链接（与 PC 侧栏底部一致，移动端由此入口访问） -->
        <div class="sticky bottom-0 bg-panel border-t border-line px-4 py-3">
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
      </AppDrawer>
    </div>

    <!-- 全局反馈体系 -->
    <AppToast />
    <AppDialog />
  </div>
</template>

<style>
.app-container {
  height: 100vh;
  height: 100dvh;
  width: 100vw;
  overflow: hidden;
  background-color: rgb(var(--c-app));
  display: flex;
  color: rgb(var(--c-hi));
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  min-width: 0;
}

@media (min-width: 1024px) {
  .main-content {
    margin-left: 14rem;
  }
}

.content-area {
  flex: 1;
  padding: 1rem;
  margin-top: 3.5rem; /* Header h-14 */
  overflow-y: auto;
  height: calc(100dvh - 3.5rem);
  display: flex;
  flex-direction: column;
}

@media (max-width: 1023px) {
  /* 给移动底部 Tab 让位 */
  .content-area {
    padding-bottom: calc(56px + env(safe-area-inset-bottom) + 0.75rem);
  }
}

@media (min-width: 1024px) {
  .content-area {
    padding: 1.5rem;
  }
}

/* 路由切换动画 */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* 确保页面内容正确显示并撑满高度 */
.content-area > div {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
</style>

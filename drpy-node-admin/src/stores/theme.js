import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  const isDark = ref(false)
  const sidebarOpen = ref(false)

  // Initialize theme: 暗色为默认主题（仪器台定位），已保存偏好优先
  const initTheme = () => {
    const saved = localStorage.getItem('theme')
    if (saved) {
      isDark.value = saved === 'dark'
    } else {
      isDark.value = true
    }
    updateTheme()
  }

  const updateTheme = () => {
    if (isDark.value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  const toggleTheme = () => {
    isDark.value = !isDark.value
  }

  const closeSidebar = () => {
    sidebarOpen.value = false
  }

  const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
  }

  // Watch for changes and save to localStorage
  watch(isDark, () => {
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
    updateTheme()
  })

  return {
    isDark,
    sidebarOpen,
    initTheme,
    toggleTheme,
    closeSidebar,
    toggleSidebar
  }
})

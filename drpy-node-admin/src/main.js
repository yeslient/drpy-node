import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './style.css'
// JetBrains Mono 自托管（构建进产物，drpys 常跑内网禁止运行时字体 CDN）
import '@fontsource/jetbrains-mono/400.css'
import '@fontsource/jetbrains-mono/500.css'
import '@fontsource/jetbrains-mono/700.css'
import { install as VueMonacoEditorPlugin } from '@guolao/vue-monaco-editor'
import UI from './components/ui'
import { useThemeStore } from './stores/theme'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(UI)

app.use(VueMonacoEditorPlugin, {
  paths: {
    // 使用 jsdelivr 的国内加速节点或者 unpkg
    vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.43.0/min/vs'
  },
  'vs/nls': {
    availableLanguages: {
      '*': 'zh-cn'
    }
  }
})

app.mount('#app')

// 应用主题（暗色默认）需在 pinia 就绪后执行
useThemeStore().initTheme()

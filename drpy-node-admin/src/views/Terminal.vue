<script>
export default {
  name: 'TerminalView'
}
</script>

<script setup>
import { ref, onMounted, onUnmounted, onActivated, onDeactivated, nextTick, watch } from 'vue'
import { Terminal } from '@xterm/xterm'
import { FitAddon } from '@xterm/addon-fit'
import '@xterm/xterm/css/xterm.css'
import { useThemeStore } from '../stores/theme'

const themeStore = useThemeStore()
const terminalContainer = ref(null)
const wsUrl = ref('')
const isConnected = ref(false)
const connectionError = ref('')

let term = null
let fitAddon = null
let ws = null
let resizeObserver = null
let isInitialized = false

// Initialize Terminal
const initTerminal = () => {
  if (isInitialized) return;
  isInitialized = true;
  term = new Terminal({
    cursorBlink: true,
    fontSize: 14,
    fontFamily: 'Menlo, Monaco, "Courier New", monospace',
    theme: getThemeColors(),
    allowProposedApi: true,
    // Enable better touch scrolling behavior
    scrollback: 5000,
    macOptionIsMeta: true,
    scrollOnUserInput: true
  })
  
  fitAddon = new FitAddon()
  term.loadAddon(fitAddon)
  
  term.open(terminalContainer.value)
  fitAddon.fit()

  // Set up mobile scrolling workaround
  const viewport = terminalContainer.value.querySelector('.xterm-viewport');
  const screen = terminalContainer.value.querySelector('.xterm-screen');
  
  if (viewport && screen) {
    let touchStartY = 0;
    let touchStartScrollTop = 0;
    
    // Delegate touch events from the screen to the viewport
    screen.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        touchStartY = e.touches[0].clientY;
        touchStartScrollTop = viewport.scrollTop;
      }
    }, { passive: true });

    screen.addEventListener('touchmove', (e) => {
      if (e.touches.length === 1) {
        const touchCurrentY = e.touches[0].clientY;
        const deltaY = touchStartY - touchCurrentY;
        
        // Prevent default to stop xterm from intercepting, but only if we actually scrolled
        if (Math.abs(deltaY) > 0) {
            // Apply delta relative to original start to avoid compounding rounding errors
            viewport.scrollTop = touchStartScrollTop + deltaY;
            if (e.cancelable) {
              e.preventDefault();
            }
        }
      }
    }, { passive: false }); // Changed back to false to allow preventDefault

    // Let xterm.js handle its own refresh on scroll
    // viewport.addEventListener('scroll', () => { ... })
  }
  
  // Handle terminal resize
  term.onResize(({ cols, rows }) => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type: 'resize', cols, rows }))
    }
  })
  
  // Handle user input
  term.onData(data => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(data)
    }
  })
  
  // Setup ResizeObserver to refit terminal when container changes
  resizeObserver = new ResizeObserver(() => {
    if (fitAddon) {
      try {
        fitAddon.fit()
        if (term) term.scrollToBottom()
      } catch (e) {
        // ignore
      }
    }
  })
  resizeObserver.observe(terminalContainer.value)
}

const getThemeColors = () => {
  const isDark = document.documentElement.classList.contains('dark')
  return {
    background: isDark ? '#0d1117' : '#eef1f4',
    foreground: isDark ? '#d4d4d4' : '#333333',
    cursor: isDark ? '#2dd4bf' : '#0d9488',
    cursorAccent: isDark ? '#0d1117' : '#eef1f4',
    selectionBackground: isDark ? '#264f78' : '#cce2ff'
  }
}

// Watch for theme changes to update terminal colors
watch(() => themeStore.isDark, () => {
  if (term) {
    term.options.theme = getThemeColors()
  }
})

// Connect to WebSocket
const connect = () => {
  if (ws) {
    ws.close()
  }
  
  connectionError.value = ''
  
  try {
    let url = wsUrl.value || getDefaultWsUrl()
    
    // Extract auth from localStorage
    const auth = localStorage.getItem('drpy_auth')
    if (auth) {
      // Append auth to query string if not already present
      const urlObj = new URL(url)
      if (!urlObj.searchParams.has('auth')) {
        urlObj.searchParams.set('auth', auth)
        url = urlObj.toString()
      }
    }

    ws = new WebSocket(url)
    
    ws.onopen = () => {
      isConnected.value = true
      term.clear()
      term.writeln('\x1b[32m[✓] Connected to terminal\x1b[0m')
      
      // Send initial resize
      if (term.cols && term.rows) {
        ws.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }))
      }
    }
    
    ws.onmessage = async (event) => {
      let data = event.data
      if (data instanceof Blob) {
        data = await data.text()
      }
      term.write(data)
    }
    
    ws.onclose = () => {
      isConnected.value = false
      term.writeln('\r\n\x1b[31m[!] Disconnected from terminal\x1b[0m')
    }
    
    ws.onerror = (error) => {
      console.error('WebSocket error:', error)
      connectionError.value = '连接失败，请检查服务地址是否正确'
      isConnected.value = false
    }
  } catch (error) {
    connectionError.value = error.message
  }
}

const disconnect = () => {
  if (ws) {
    ws.close()
  }
}

const getDefaultWsUrl = () => {
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
  const host = window.location.host
  return `${protocol}//${host}/api/admin/terminal/ws`
}

const preventGlobalScroll = () => {
  if (window.scrollY > 0 || window.scrollX > 0) {
    window.scrollTo(0, 0);
  }
}

onMounted(() => {
  wsUrl.value = getDefaultWsUrl()
  
  nextTick(() => {
    initTerminal()
    // Auto connect on load
    if (!isConnected.value) {
      connect()
    }
  })
})

onActivated(() => {
  window.addEventListener('scroll', preventGlobalScroll, { passive: true })
  
  // Terminal re-fit when component becomes visible again
  if (fitAddon) {
    nextTick(() => {
      try {
        fitAddon.fit()
      } catch(e) {}
    })
  }
})

onDeactivated(() => {
  window.removeEventListener('scroll', preventGlobalScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', preventGlobalScroll)
  
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
  if (ws) {
    ws.close()
  }
  if (term) {
    term.dispose()
  }
})
</script>

<template>
  <div class="terminal-page flex flex-col space-y-3">
    <PageHeader eyebrow="// TERMINAL" title="终端模拟" desc="连接 drpys 服务端 WebSocket 终端（移动端建议横屏使用）">
      <template #actions>
        <span class="inline-flex items-center gap-1.5 px-2.5 h-8 rounded-full border text-xs" :class="isConnected ? 'border-ok/30 bg-ok/10 text-ok' : 'border-line text-low'">
          <StatusDot :tone="isConnected ? 'ok' : 'off'" />
          {{ isConnected ? '已连接' : '未连接' }}
        </span>
      </template>
    </PageHeader>

    <!-- 连接控制 -->
    <div class="panel p-3 shrink-0" style="--i: 0">
      <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center">
        <div class="flex-1">
          <label class="block text-2xs text-low mb-1 font-mono">WEBSOCKET URL</label>
          <div class="flex gap-2">
            <input
              v-model="wsUrl"
              type="text"
              class="input h-10 flex-1 font-mono text-xs"
              placeholder="ws://localhost:3000/api/admin/terminal/ws"
              spellcheck="false"
              autocomplete="off"
              @keyup.enter="connect"
            >
            <UButton v-if="!isConnected" variant="primary" @click="connect">连接</UButton>
            <UButton v-else variant="danger" @click="disconnect">断开</UButton>
          </div>
          <p v-if="connectionError" class="mt-1.5 text-xs text-danger">{{ connectionError }}</p>
        </div>
      </div>
    </div>

    <!-- 终端容器：凹面 -->
    <div class="terminal-content flex-1 panel overflow-hidden flex flex-col min-h-0 relative" style="--i: 1">
      <div class="h-8 shrink-0 bg-sunken border-b border-line flex items-center px-4 gap-1.5 select-none">
        <div class="w-2.5 h-2.5 rounded-full bg-danger/70" />
        <div class="w-2.5 h-2.5 rounded-full bg-warn/70" />
        <div class="w-2.5 h-2.5 rounded-full bg-ok/70" />
        <div class="mx-auto eyebrow">terminal</div>
      </div>

      <div class="flex-1 relative w-full overflow-hidden terminal-wrapper bg-sunken" style="touch-action: none;">
        <div class="absolute inset-0 p-2">
          <div ref="terminalContainer" class="w-full h-full" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.terminal-page {
  display: flex;
  flex-direction: column;
  height: calc(100dvh - 8rem);
}
</style>

<style>
.terminal-wrapper {
  /* Ensure xterm takes full height and width */
}
.terminal-wrapper .xterm-viewport {
  /* custom scrollbar for terminal */
  scrollbar-width: thin;
  /* Improve scrolling on touch devices */
  -webkit-overflow-scrolling: touch;
}
.terminal-wrapper .xterm-screen {
  /* Improve scrolling on touch devices */
  z-index: 1;
}

/* Ensure the page doesn't scroll globally when focusing the terminal */
body:has(.terminal-page) {
  overflow: hidden !important;
  width: 100%;
  height: 100%;
}
.xterm-viewport {
  background-color: transparent !important; /* Help with rendering glitches */
}
</style>
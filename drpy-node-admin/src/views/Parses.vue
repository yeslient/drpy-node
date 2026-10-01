<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fileApi } from '../api/file'

const router = useRouter()
const loading = ref(false)
const jxFiles = ref([])

onMounted(async () => {
  await loadJxFiles()
})

const loadJxFiles = async () => {
  loading.value = true
  try {
    const result = await fileApi.listDirectory('jx')
    jxFiles.value = (result.files || []).filter(f => !f.isDirectory && f.name.endsWith('.js'))
  } catch (e) {
    console.error('Load jx files error:', e)
    // Maybe the directory doesn't exist, we can ignore or show empty
    jxFiles.value = []
  } finally {
    loading.value = false
  }
}

const editConfig = () => {
  router.push({
    name: 'source-editor',
    query: { path: 'config/parses.conf' }
  })
}

const editFile = (file) => {
  router.push({
    name: 'source-editor',
    query: { path: `jx/${file.name}` }
  })
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// PARSES" title="解析管理" desc="管理系统解析配置文件及 jx 目录下的解析脚本">
      <template #actions>
        <UButton size="sm" :loading="loading" @click="loadJxFiles">
          <svg v-if="!loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          刷新
        </UButton>
      </template>
    </PageHeader>

    <div class="flex-1 overflow-y-auto min-h-0 space-y-4 enter-stagger">
      <!-- 全局解析配置 -->
      <section style="--i: 0">
        <div class="eyebrow mb-2">// 全局解析配置</div>
        <button class="panel w-full p-4 flex items-center justify-between hover:border-accent/40 transition-colors text-left group" @click="editConfig">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-ctrl bg-info/10 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <div class="min-w-0">
              <h4 class="text-[13px] font-medium text-hi">parses.conf</h4>
              <p class="text-xs text-low font-mono truncate">drpy-node/config/parses.conf</p>
            </div>
          </div>
          <span class="text-xs text-accent shrink-0 group-hover:translate-x-0.5 transition-transform">编辑 →</span>
        </button>
      </section>

      <!-- JS 解析脚本 -->
      <section style="--i: 1">
        <div class="eyebrow mb-2">// JS 解析脚本（jx 目录 · {{ jxFiles.length }}）</div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
          <USkeleton v-for="i in 3" :key="i" variant="panel" />
        </div>

        <UEmpty v-else-if="jxFiles.length === 0" class="panel" icon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" text="jx 目录下没有找到 JS 解析脚本" />

        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
          <button
            v-for="file in jxFiles"
            :key="file.name"
            class="panel p-3.5 flex items-center gap-3 hover:border-accent/40 transition-colors text-left"
            @click="editFile(file)"
          >
            <div class="w-9 h-9 rounded-ctrl bg-warn/10 flex items-center justify-center shrink-0">
              <span class="text-warn font-mono font-bold text-xs">JS</span>
            </div>
            <div class="min-w-0 flex-1">
              <h4 class="text-[13px] font-medium text-hi truncate" :title="file.name">{{ file.name }}</h4>
              <p class="text-2xs text-low font-mono">{{ (file.size / 1024).toFixed(2) }} KB</p>
            </div>
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

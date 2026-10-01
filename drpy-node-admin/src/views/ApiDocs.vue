<script setup>
import { ref, computed, onMounted } from 'vue'
import { systemApi } from '../api/system'

const apiList = ref([])
const loading = ref(false)
const expandedCategory = ref(null)
const searchQuery = ref('')

onMounted(async () => {
  await loadApiList()
})

const loadApiList = async () => {
  loading.value = true
  try {
    const result = await systemApi.getApiList()
    apiList.value = result || []
  } catch (e) {
    console.error('Failed to load API list:', e)
  } finally {
    loading.value = false
  }
}

const filteredCategories = computed(() => {
  if (!searchQuery.value) return apiList.value

  return apiList.value.map(category => ({
    ...category,
    endpoints: category.endpoints.filter(endpoint => {
      const searchLower = searchQuery.value.toLowerCase()
      return endpoint.path?.toLowerCase().includes(searchLower) ||
             endpoint.description?.toLowerCase().includes(searchLower) ||
             endpoint.method?.toLowerCase().includes(searchLower)
    })
  })).filter(category => category.endpoints.length > 0)
})

const toggleCategory = (category) => {
  if (expandedCategory.value === category) {
    expandedCategory.value = null
  } else {
    expandedCategory.value = category
  }
}

const METHOD_TONES = {
  GET: 'ok',
  POST: 'info',
  PUT: 'warn',
  DELETE: 'danger',
  ALL: 'accent',
}

// 视图切换：Swagger UI（OpenAPI 规范渲染）为主视图，列表为中文速览
const SWAGGER_URL = '/apps/admin/api-docs/swagger/'
const viewMode = ref('swagger')
const viewTabs = [
  { key: 'swagger', label: 'Swagger UI' },
  { key: 'list', label: '接口列表' },
]
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// API DOCS" title="API 文档" desc="drpy-node 可用的 API 接口文档">
      <template #actions>
        <div v-if="viewMode === 'list'" class="relative w-full sm:w-56">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-low" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input v-model="searchQuery" type="text" class="input h-9 pl-9 pr-8" placeholder="搜索 API 端点…">
          <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
        </div>
      </template>
    </PageHeader>

    <UTabs v-model="viewMode" :tabs="viewTabs" class="shrink-0" />

    <!-- Swagger UI（iframe 隔离其全局样式，嵌入内容区域） -->
    <iframe
      v-if="viewMode === 'swagger'"
      :src="SWAGGER_URL"
      class="flex-1 w-full min-h-0 border-0"
      title="Swagger UI"
    ></iframe>

    <div v-else class="flex-1 overflow-y-auto min-h-0 space-y-3 enter-stagger">
      <div v-if="loading" class="space-y-3">
        <USkeleton v-for="i in 4" :key="i" variant="panel" />
      </div>

      <UEmpty v-else-if="filteredCategories.length === 0" text="没有匹配的 API 端点" />

      <div v-for="(category, ci) in filteredCategories" v-else :key="category.category" class="panel overflow-hidden" :style="{ '--i': Math.min(ci, 8) }">
        <button
          class="w-full px-4 py-3 flex items-center justify-between hover:bg-mid/[0.03] transition-colors"
          @click="toggleCategory(category.category)"
        >
          <h3 class="text-[13px] font-semibold text-hi flex items-center gap-2">
            <span class="w-1 h-3.5 bg-accent rounded-full" />
            {{ category.category }}
          </h3>
          <div class="flex items-center gap-2">
            <span class="font-mono text-2xs text-low">{{ category.endpoints.length }}</span>
            <svg
              class="w-4 h-4 text-low transition-transform"
              :class="{ 'rotate-180': expandedCategory === category.category }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </button>

        <div v-if="expandedCategory === category.category" class="divide-y divide-line/70 border-t border-line">
          <div v-for="endpoint in category.endpoints" :key="endpoint.path" class="px-4 py-4">
            <div class="flex items-start gap-3">
              <UBadge :tone="METHOD_TONES[endpoint.method?.toUpperCase()] || 'neutral'" class="shrink-0 mt-0.5">{{ endpoint.method }}</UBadge>

              <div class="flex-1 min-w-0">
                <code class="text-xs font-mono text-accent break-all">{{ endpoint.path }}</code>
                <p class="text-xs text-mid mt-1">{{ endpoint.description }}</p>

                <div v-if="endpoint.params && Object.keys(endpoint.params).length > 0" class="mt-3">
                  <h4 class="text-2xs text-low mb-1.5">参数</h4>
                  <div class="bg-sunken rounded-ctrl p-3 space-y-2">
                    <div v-for="(param, key) in endpoint.params" :key="key" class="flex items-start gap-2.5 text-xs">
                      <code class="px-1.5 py-0.5 bg-mid/10 rounded text-2xs font-mono text-mid shrink-0">{{ param.type || key }}</code>
                      <div class="min-w-0">
                        <span class="font-medium text-hi">{{ key }}</span>
                        <span v-if="param.required" class="ml-1 text-danger">*</span>
                        <p class="text-low mt-0.5">{{ param.description }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

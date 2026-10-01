<script setup>
import { ref, computed, onMounted } from 'vue'
import { useConfigStore } from '../stores/config'
import { useToast } from '../composables/useToast'

const configStore = useConfigStore()
const toast = useToast()
const editingKey = ref(null)
const editValue = ref('')

const searchQuery = ref('')
const selectedGroup = ref('全部')

// Config categories and descriptions
const configDescriptions = {
  // Cloud Storage
  quark_cookie: '夸克网盘 Cookie，用于夸克网盘资源解析',
  quark_token_cookie: '夸克网盘 Token',
  uc_cookie: 'UC 网盘 Cookie',
  uc_token_cookie: 'UC 网盘 Token',
  ali_token: '阿里云盘 Token',
  ali_refresh_token: '阿里云盘刷新令牌',
  pikpak_token: 'PikPak Token',
  xun_username: '迅雷账号',
  xun_password: '迅雷密码',
  xun_auth: '迅雷认证令牌(登录成功自动获取)',
  yun_account: '移动云盘账号',
  yun_cookie: '移动云盘 Cookie',

  // Video Platform
  bili_cookie: 'Bilibili Cookie，用于 B站资源解析',

  // AI Services
  spark_ai_authKey: '讯飞星火 AI 认证密钥',
  deepseek_apiKey: 'DeepSeek API 密钥',
  kimi_apiKey: 'Kimi API 密钥',
  now_ai: '当前使用的 AI 服务 (1=讯飞, 2=DeepSeek, 3=Kimi)',
  sparkBotObject: '讯飞机器人配置对象',

  // Proxy Settings
  enable_system_proxy: '启用系统代理 (0=禁用, 1=启用)',
  PROXY_AUTH: '代理认证密码',
  play_proxy_mode: '播放代理模式',
  play_local_proxy_type: '本地代理类型',
  enable_doh: '启用 DoH (0=禁用, 1=启用)',
  allow_ftp_cache_clear: '允许 FTP 缓存清理 (0=禁用, 1=启用)',
  allow_webdav_cache_clear: '允许 WebDAV 缓存清理 (0=禁用, 1=启用)',

  // Cloud Account
  cloud_account: '云盘账号',
  cloud_password: '云盘密码',
  cloud_cookie: '云盘 Cookie',
  pan_passport: '网盘账号',
  pan_password: '网盘密码',
  pan_auth: '网盘认证令牌',

  // Other Settings
  thread: '并发线程数',
  api_pwd: 'API 访问密码',
  allow_forward: '允许转发 (0=禁用, 1=启用)',
  link_url: '链接推送 URL',
  enable_link_data: '启用链接数据',
  enable_link_push: '启用链接推送',
  enable_link_jar: '启用 JAR 链接',
  mg_hz: 'MG 赫兹设置',
  baidu_cookie: '百度 Cookie，用于百度网盘',
  enable_rule_name: '显示规则名称 (0=禁用, 1=启用)',
  must_sub_code: '强制订阅代码 (0=禁用, 1=启用)',

  // Engine Settings
  enable_dr2: '启用 DR2 引擎 (0=禁用, 1=启用)',
  enable_py: '启用 Python 引擎 (0=禁用, 1=启用, 2=自动)',
  enable_php: '启用 PHP 引擎 (0=禁用, 1=启用)',
  enable_cat: '启用 CatVod 引擎 (0=禁用, 1=启用, 2=自动)',
  enable_old_config: '启用旧配置格式',

  // House Repository
  HOUSE_TOKEN: 'DS源仓库认证 Token（JWT，对应 Bearer 后的部分）',
  HOUSER_URL: 'DS源仓库地址（默认 http://183.87.133.60:5678/）',

  // MCP Auth
  MCP_TOKEN: 'MCP 远程服务认证 Token（SSE/HTTP 模式下客户端需携带 Authorization: Bearer <此值>）',

  // Live Settings
  LIVE_URL: '直播订阅链接（支持多个：用 ; 或换行分隔，每个链接一个直播入口）；留空时仅使用 json/lives 目录源',
  EPG_URL: '直播 EPG 节目单地址',
  LOGO_URL: '直播台标地址',

  // Debug
  show_curl: '显示 cURL 命令',
  show_req: '显示请求详情',
  hide_adult: '隐藏成人内容 (0=显示, 1=隐藏)',
  enable_self_jx: '启用自建解析',
  cat_sub_code: 'CatVod 订阅代码'
}

const groups = {
  '云盘配置': ['quark_cookie', 'quark_token_cookie', 'uc_cookie', 'uc_token_cookie', 'ali_token', 'ali_refresh_token', 'pikpak_token', 'cloud_account', 'cloud_password', 'cloud_cookie', 'yun_account', 'yun_cookie', 'pan_passport', 'pan_password', 'pan_auth', 'xun_username', 'xun_password', 'xun_auth', 'xun_user_id', 'device_id', 'xun_refresh_token'],
  '视频平台': ['bili_cookie', 'baidu_cookie'],
  'AI 服务': ['spark_ai_authKey', 'deepseek_apiKey', 'kimi_apiKey', 'now_ai', 'sparkBotObject'],
  '代理设置': ['enable_system_proxy', 'PROXY_AUTH', 'play_proxy_mode', 'play_local_proxy_type', 'enable_doh', 'allow_forward', 'allow_ftp_cache_clear', 'allow_webdav_cache_clear', 'unified_proxy_self_redirect'],
  '引擎设置': ['enable_dr2', 'enable_py', 'enable_php', 'enable_cat', 'enable_old_config', 'enable_self_jx'],
  '仓库配置': ['HOUSE_TOKEN', 'HOUSER_URL'],
  'MCP 认证': ['MCP_TOKEN'],
  '直播配置': ['LIVE_URL', 'EPG_URL', 'LOGO_URL'],
  '其他设置': ['thread', 'api_pwd', 'link_url', 'enable_link_data', 'enable_link_push', 'enable_link_jar', 'mg_hz', 'show_curl', 'show_req', 'enable_rule_name', 'hide_adult', 'cat_sub_code', 'must_sub_code']
}

// All groups including "未分类配置" if any
const allGroups = computed(() => {
  const result = { ...groups }
  if (configStore.config) {
    const categorizedKeys = new Set(Object.values(groups).flat())
    const uncategorized = Object.keys(configStore.config).filter(k => !categorizedKeys.has(k))
    if (uncategorized.length > 0) {
      result['未分类配置'] = uncategorized
    }
  }
  return result
})

const groupNames = computed(() => ['全部', ...Object.keys(allGroups.value)])

const filteredConfigs = computed(() => {
  if (!configStore.config) return {}

  const result = {}
  const query = searchQuery.value.toLowerCase().trim()

  for (const [groupName, keys] of Object.entries(allGroups.value)) {
    if (selectedGroup.value !== '全部' && selectedGroup.value !== groupName) {
      continue
    }

    const groupResult = {}
    for (const key of keys) {
      if (configStore.config) {
        const value = key in configStore.config ? String(configStore.config[key]) : ''
        const desc = configDescriptions[key] || ''

        if (!query || key.toLowerCase().includes(query) || desc.toLowerCase().includes(query)) {
          groupResult[key] = key in configStore.config ? configStore.config[key] : ''
        }
      }
    }

    if (Object.keys(groupResult).length > 0) {
      result[groupName] = groupResult
    }
  }
  return result
})

const hasFilteredResults = computed(() => Object.keys(filteredConfigs.value).length > 0)

onMounted(() => {
  configStore.fetchConfig()
})

const startEdit = (key, value) => {
  editingKey.value = key
  editValue.value = typeof value === 'object' ? JSON.stringify(value, null, 2) : value
}

const cancelEdit = () => {
  editingKey.value = null
  editValue.value = ''
}

const saveEdit = async (key) => {
  try {
    await configStore.updateConfig(key, editValue.value)
    toast.success(`${key} 已保存`)
    editingKey.value = null
    editValue.value = ''
  } catch (e) {
    toast.error('保存失败: ' + e.message)
  }
}

const maskSensitive = (key, value) => {
  if (!value) return value
  const sensitiveKeys = ['cookie', 'token', 'password', 'authKey', 'apiKey', 'auth']
  const isSensitive = sensitiveKeys.some(s => key.toLowerCase().includes(s))

  if (isSensitive && typeof value === 'string' && value.length > 8) {
    return value.substring(0, 4) + '****' + value.substring(value.length - 4)
  }
  return value
}

const isSensitiveKey = (key) => {
  const sensitiveKeys = ['cookie', 'token', 'password', 'authKey', 'apiKey', 'auth']
  return sensitiveKeys.some(s => key.toLowerCase().includes(s))
}

const formatDisplayValue = (value) => {
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }
  return String(value)
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// CONFIG" title="环境配置" desc="管理 drpy-node 的环境变量和配置项">
      <template #actions>
        <div class="relative w-full sm:w-56">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-low" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input v-model="searchQuery" type="text" class="input h-9 pl-9 pr-8" placeholder="搜索变量名或描述…">
          <button v-if="searchQuery" class="absolute inset-y-0 right-0 pr-2.5 text-low hover:text-hi" @click="searchQuery = ''">✕</button>
        </div>
        <UButton size="sm" :loading="configStore.loading" @click="configStore.fetchConfig()">
          <svg v-if="!configStore.loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          刷新
        </UButton>
      </template>
    </PageHeader>

    <!-- 分组 chips：移动横滑 / PC 换行 -->
    <div class="flex overflow-x-auto hide-scrollbar gap-1.5 pb-3 shrink-0">
      <button
        v-for="group in groupNames" :key="group"
        class="px-3 h-8 rounded-full text-xs whitespace-nowrap transition-colors border shrink-0"
        :class="selectedGroup === group
          ? 'bg-accent-dim text-accent border-accent/40'
          : 'text-mid border-line hover:text-hi hover:border-mid/40'"
        @click="selectedGroup = group"
      >{{ group }}</button>
    </div>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto min-h-0 space-y-4 enter-stagger">
      <!-- Loading state -->
      <div v-if="configStore.loading && !configStore.config" class="space-y-4">
        <USkeleton v-for="i in 3" :key="i" variant="panel" />
      </div>

      <!-- Error state -->
      <div v-else-if="configStore.error" class="panel p-6 border-danger/30">
        <p class="text-danger text-sm">加载配置失败: {{ configStore.error }}</p>
      </div>

      <!-- No Results State -->
      <UEmpty v-else-if="!hasFilteredResults" class="panel" icon="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" text="未找到匹配的配置项，请尝试更换搜索词或选择其他分类">
        <UButton v-if="searchQuery || selectedGroup !== '全部'" size="sm" variant="soft" @click="searchQuery = ''; selectedGroup = '全部'">清除筛选</UButton>
      </UEmpty>

      <!-- Config groups -->
      <div v-for="(configs, groupName, gi) in filteredConfigs" v-else :key="groupName" class="panel overflow-hidden" :style="{ '--i': Math.min(gi, 8) }">
        <div class="px-4 py-3 border-b border-line flex items-center justify-between bg-sunken/50">
          <h3 class="text-[13px] font-semibold text-hi flex items-center gap-2">
            <span class="w-1 h-3.5 bg-accent rounded-full" />
            {{ groupName }}
          </h3>
          <span class="font-mono text-2xs text-low">{{ Object.keys(configs).length }} 项</span>
        </div>

        <div class="divide-y divide-line/70">
          <div
            v-for="(value, key) in configs" :key="key"
            class="p-4 hover:bg-mid/[0.03] transition-colors group"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                  <h4 class="font-mono text-[13px] font-semibold text-hi break-all">{{ key }}</h4>
                  <UBadge v-if="isSensitiveKey(key)" tone="warn" title="敏感项，展示已掩码">敏感</UBadge>
                </div>

                <p v-if="configDescriptions[key]" class="text-xs text-low mb-2">{{ configDescriptions[key] }}</p>

                <!-- Display value -->
                <div v-if="editingKey !== key">
                  <code class="block p-2.5 text-xs font-mono text-mid bg-sunken border border-line rounded-ctrl break-all whitespace-pre-wrap max-h-32 overflow-y-auto sunken-scroll cursor-text group-hover:border-accent/30 transition-colors" title="点击编辑" @click="startEdit(key, value)">
                    {{ maskSensitive(key, formatDisplayValue(value)) || ' ' }}
                  </code>
                </div>

                <!-- Edit form -->
                <div v-else class="mt-2 p-3 bg-accent-dim border border-accent/30 rounded-ctrl">
                  <label class="block text-2xs font-mono text-accent mb-2">EDIT {{ key }}</label>
                  <textarea
                    v-model="editValue"
                    class="input min-h-[100px] font-mono text-xs resize-y"
                    :placeholder="'输入 ' + key + ' 的值'"
                  />
                  <div class="flex flex-wrap gap-2 mt-3">
                    <UButton size="sm" variant="primary" @click="saveEdit(key)">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                      保存
                    </UButton>
                    <UButton size="sm" @click="cancelEdit">取消</UButton>
                  </div>
                </div>
              </div>

              <!-- Edit action（桌面悬停显示） -->
              <button
                v-if="editingKey !== key"
                class="hidden sm:flex p-2 rounded-ctrl text-low hover:text-accent hover:bg-accent-dim transition-colors opacity-0 group-hover:opacity-100 shrink-0"
                title="编辑此项"
                @click="startEdit(key, value)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 隐藏滚动条但保留滚动功能 */
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>

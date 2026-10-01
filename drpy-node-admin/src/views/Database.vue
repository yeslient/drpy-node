<script setup>
import { ref } from 'vue'
import { dbApi } from '../api/db'

const sqlQuery = ref('')
const queryResult = ref(null)
const loading = ref(false)
const error = ref(null)

// Example queries
const exampleQueries = [
  { name: '查看所有表', sql: "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;" },
  { name: '查看表结构', sql: "PRAGMA table_info(table_name);" },
  { name: '查询前10条记录', sql: "SELECT * FROM table_name LIMIT 10;" },
]

const executeQuery = async () => {
  if (!sqlQuery.value.trim()) {
    return
  }

  loading.value = true
  error.value = null
  queryResult.value = null

  try {
    const result = await dbApi.query(sqlQuery.value)
    queryResult.value = result
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const useExample = (sql) => {
  sqlQuery.value = sql
}
</script>

<template>
  <div class="h-full flex flex-col min-h-0">
    <PageHeader eyebrow="// DATABASE" title="数据库" desc="执行只读 SQL 查询（SQLite）">
      <template #actions>
        <UButton size="sm" variant="primary" :loading="loading" :disabled="!sqlQuery.trim()" @click="executeQuery">
          <svg v-if="!loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          执行查询
        </UButton>
      </template>
    </PageHeader>

    <div class="flex-1 overflow-y-auto min-h-0 space-y-3 enter-stagger">
      <!-- 示例查询 -->
      <div class="flex flex-wrap gap-1.5 items-center" style="--i: 0">
        <span class="eyebrow mr-1">示例</span>
        <button
          v-for="example in exampleQueries" :key="example.name"
          class="px-2.5 h-8 text-xs rounded-full border border-line text-mid hover:text-accent hover:border-accent/40 transition-colors"
          @click="useExample(example.sql)"
        >{{ example.name }}</button>
      </div>

      <!-- SQL 编辑区 -->
      <div class="panel overflow-hidden" style="--i: 1">
        <div class="px-4 pt-3 pb-2">
          <textarea
            v-model="sqlQuery"
            placeholder="输入 SQL 查询语句…"
            class="w-full min-h-[110px] p-3 bg-sunken border border-line rounded-ctrl font-mono text-xs text-hi resize-y focus:outline-none focus:ring-2 focus:ring-accent/50"
            spellcheck="false"
            @keydown.ctrl.enter="executeQuery"
            @keydown.meta.enter="executeQuery"
          />
          <div class="flex items-center justify-between mt-2">
            <span class="text-2xs text-low font-mono">CTRL+ENTER 执行</span>
          </div>
        </div>

        <!-- 错误 -->
        <div v-if="error" class="px-4 py-3 bg-danger/10 border-t border-danger/25">
          <p class="text-danger font-mono text-xs break-all">{{ error }}</p>
        </div>

        <!-- 结果表格 -->
        <div v-if="queryResult" class="overflow-x-auto border-t border-line">
          <table v-if="Array.isArray(queryResult) && queryResult.length > 0" class="w-full text-xs">
            <thead>
              <tr class="bg-sunken">
                <th
                  v-for="column in Object.keys(queryResult[0])" :key="column"
                  class="px-3 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.12em] text-low font-medium border-b border-line whitespace-nowrap"
                >{{ column }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line/60">
              <tr v-for="(row, index) in queryResult" :key="index" class="hover:bg-mid/[0.04]">
                <td v-for="column in Object.keys(queryResult[0])" :key="column" class="px-3 py-2 font-mono text-mid break-all max-w-[320px]">
                  {{ row[column] }}
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="p-8 text-center text-low text-xs">查询成功，无结果返回</div>

          <div v-if="Array.isArray(queryResult)" class="px-4 py-2.5 border-t border-line font-mono text-2xs text-low">
            {{ queryResult.length }} ROWS
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

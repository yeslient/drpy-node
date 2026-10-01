<script setup>
// 自适应数据列表：≥md 渲染表格 / <md 渲染卡片堆叠（表格移动化的统一解法）
// columns: [{ key, label, mono?, class? }]
// 单元格自定义：#cell-<key>="{ row, value }"；移动卡片自定义：#card="{ row }"（缺省为键值对列表）
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <!-- 桌面表格 -->
  <div class="hidden md:block overflow-x-auto">
    <table class="w-full text-[13px]">
      <thead>
        <tr class="border-b border-line">
          <th
            v-for="col in columns"
            :key="col.key"
            class="text-left font-mono text-[10px] uppercase tracking-[0.12em] text-low font-medium px-3 py-2.5 whitespace-nowrap"
          >{{ col.label }}</th>
          <th v-if="$slots.actions" class="px-3 py-2.5 text-right font-mono text-[10px] uppercase tracking-[0.12em] text-low whitespace-nowrap">操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row[rowKey]" class="border-b border-line/60 last:border-0 hover:bg-mid/5 transition-colors">
          <td
            v-for="col in columns"
            :key="col.key"
            class="px-3 py-2.5 align-middle"
            :class="[col.mono ? 'font-mono text-xs' : '', col.class || '']"
          >
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
              <span class="text-hi">{{ row[col.key] ?? '—' }}</span>
            </slot>
          </td>
          <td v-if="$slots.actions" class="px-3 py-2.5 text-right whitespace-nowrap">
            <slot name="actions" :row="row" />
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="!loading && !rows.length" class="text-center text-low text-xs py-8">暂无数据</p>
    <div v-if="loading" class="py-8 space-y-2.5 animate-pulse">
      <div v-for="i in 3" :key="i" class="h-8 rounded bg-mid/10" />
    </div>
  </div>

  <!-- 移动卡片堆叠 -->
  <div class="md:hidden space-y-2.5">
    <div v-for="row in rows" :key="row[rowKey]" class="panel p-3.5">
      <slot name="card" :row="row">
        <div class="space-y-1.5">
          <div v-for="col in columns" :key="col.key" class="flex items-start justify-between gap-3">
            <span class="text-2xs text-low shrink-0 pt-0.5">{{ col.label }}</span>
            <span class="text-[13px] text-hi text-right break-all min-w-0" :class="{ 'font-mono text-xs': col.mono }">
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] ?? '—' }}</slot>
            </span>
          </div>
        </div>
      </slot>
      <div v-if="$slots.actions" class="mt-3 pt-3 border-t border-line flex justify-end gap-2">
        <slot name="actions" :row="row" />
      </div>
    </div>
    <div v-if="!loading && !rows.length" class="text-center text-low text-xs py-8">暂无数据</div>
    <div v-if="loading" class="space-y-2.5 animate-pulse">
      <div v-for="i in 3" :key="i" class="panel h-20" />
    </div>
  </div>
</template>

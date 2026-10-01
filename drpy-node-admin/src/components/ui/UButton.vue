<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'secondary' }, // primary / secondary / ghost / danger / soft
  size: { type: String, default: 'md' }, // sm / md / lg
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})

const variantClass = computed(() => ({
  primary: 'bg-accent text-[#04211d] dark:text-[#04211d] hover:brightness-110 active:translate-y-px font-medium html-light-white',
  secondary: 'border border-line bg-panel text-hi hover:bg-sunken active:translate-y-px',
  ghost: 'text-mid hover:text-hi hover:bg-mid/10 active:translate-y-px',
  danger: 'bg-danger text-white hover:brightness-110 active:translate-y-px',
  soft: 'bg-accent-dim text-accent border border-accent/30 hover:border-accent/60 active:translate-y-px',
}[props.variant] || ''))

const sizeClass = computed(() => ({
  sm: 'h-8 px-2.5 text-xs gap-1 rounded-ctrl',
  md: 'h-10 px-3.5 text-[13px] gap-1.5 rounded-ctrl',
  lg: 'h-11 px-5 text-sm gap-2 rounded-ctrl',
}[props.size] || ''))
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap"
    :class="[variantClass, sizeClass, { 'w-full': block }]"
  >
    <svg v-if="loading" class="animate-spin w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <slot />
  </button>
</template>

<style scoped>
/* 亮色主题下 primary 底色深（teal-600），文字用白；暗色下 accent 亮青，文字用深墨绿 */
:global(html:not(.dark)) .html-light-white {
  color: #ffffff;
}
</style>

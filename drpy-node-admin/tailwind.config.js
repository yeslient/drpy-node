/** @type {import('tailwindcss').Config} */
// Instrument Panel 设计 token：语义色由 CSS 变量驱动（style.css :root 暗色 / html:not(.dark) 亮色），
// Tailwind 侧只做映射，组件里用 bg-panel / text-hi / border-line 等语义类，双主题自动生效。
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // 基底三档
        app: 'rgb(var(--c-app) / <alpha-value>)',
        panel: 'rgb(var(--c-panel) / <alpha-value>)',
        sunken: 'rgb(var(--c-sunken) / <alpha-value>)',
        // hairline 边线（本身带透明度，不走 alpha 通道）
        line: 'var(--c-line)',
        // 文字三档
        hi: 'rgb(var(--c-hi) / <alpha-value>)',
        mid: 'rgb(var(--c-mid) / <alpha-value>)',
        low: 'rgb(var(--c-low) / <alpha-value>)',
        // 信号色：只给可交互元素、状态语义与数据高亮
        accent: {
          DEFAULT: 'rgb(var(--c-accent) / <alpha-value>)',
          dim: 'var(--c-accent-dim)',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
        },
        ok: 'rgb(var(--c-ok) / <alpha-value>)',
        warn: 'rgb(var(--c-warn) / <alpha-value>)',
        danger: 'rgb(var(--c-danger) / <alpha-value>)',
        info: 'rgb(var(--c-info) / <alpha-value>)',
        // 迁移期兼容：旧视图的 primary/sky 系统一重映射为 accent(teal) 色阶，未迁移页面视觉自动趋同
        primary: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
      },
      fontFamily: {
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
        sans: ['"PingFang SC"', '"Microsoft YaHei"', '"Noto Sans SC"', 'system-ui', '-apple-system', 'sans-serif'],
      },
      fontSize: {
        // 层次规范：正文 13px / 辅助 11-12px / 眉题 10px
        '2xs': ['0.6875rem', { lineHeight: '1rem' }],
      },
      borderRadius: {
        // 圆角收紧：控件 6px、面板 8px
        ctrl: '0.375rem',
        panel: '0.5rem',
      },
      animation: {
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'spin-slow': 'spin 3s linear infinite',
        'dot-breath': 'dotBreath 2s ease-in-out infinite',
        'dot-error': 'dotError 0.9s ease-in-out infinite',
        'fade-up': 'fadeUp 0.35s ease both',
        'toast-in': 'toastIn 0.2s ease both',
        'dialog-in': 'dialogIn 0.15s ease both',
      },
      keyframes: {
        dotBreath: {
          '0%, 100%': { opacity: '1', transform: 'scale(1)' },
          '50%': { opacity: '0.45', transform: 'scale(0.82)' },
        },
        dotError: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.25' },
        },
        fadeUp: {
          from: { opacity: '0', transform: 'translateY(8px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        toastIn: {
          from: { opacity: '0', transform: 'translateY(-8px) scale(0.97)' },
          to: { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
        dialogIn: {
          from: { opacity: '0', transform: 'scale(0.96)' },
          to: { opacity: '1', transform: 'scale(1)' },
        },
      },
    },
  },
  plugins: [],
}

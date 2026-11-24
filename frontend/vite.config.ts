/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const backend = process.env.LEONIDA_BACKEND ?? 'http://127.0.0.1:8000'

// Build landet in backend/public/spa und wird von Laravel für alle Seiten-URLs ausgeliefert.
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/spa/' : '/',
  plugins: [vue()],
  build: {
    outDir: '../backend/public/spa',
    emptyOutDir: true,
    chunkSizeWarningLimit: 800,
  },
  server: {
    port: 5173,
    proxy: Object.fromEntries(['/api', '/media', '/map', '/docs'].map((p) => [p, { target: backend, changeOrigin: false }])),
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.ts'],
  },
}))

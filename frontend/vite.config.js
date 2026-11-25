import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

/**
 * Konfigurasi Vite untuk AniCine v2.0 Frontend SPA (Port 8084 — Lokal & VM Azure).
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 8084,
    strictPort: true,
    allowedHosts: ['anicine.vulnera.web.id', '.vulnera.web.id'],
  },
  preview: {
    host: '0.0.0.0',
    port: 8084,
    strictPort: true,
    allowedHosts: ['anicine.vulnera.web.id', '.vulnera.web.id'],
  },
});

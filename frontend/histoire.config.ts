import { defineConfig } from 'histoire'
import { HstVue } from '@histoire/plugin-vue'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [HstVue()],
  setupFile: './histoire.setup.ts',
  theme: { title: 'Suto Design System' },
  vite: { plugins: [vue()] },
})

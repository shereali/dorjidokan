import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 1,
  timeout: 60000,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:3000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: [
    {
      command: 'php artisan migrate:fresh --seed && php artisan serve --host=127.0.0.1 --port=8010',
      cwd: '../backend',
      env: { APP_URL: 'http://127.0.0.1:8010', FRONTEND_URLS: 'http://127.0.0.1:3000', SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:3000,127.0.0.1:8010' },
      url: 'http://127.0.0.1:8010/up',
      reuseExistingServer: false,
      timeout: 120000,
    },
    {
      command: 'npm run dev -- --host 127.0.0.1 --port 3000',
      cwd: '.',
      env: { NUXT_PUBLIC_API_BASE: 'http://127.0.0.1:8010/api/v1' },
      url: 'http://127.0.0.1:3000',
      reuseExistingServer: false,
      timeout: 120000,
    },
  ],
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
})

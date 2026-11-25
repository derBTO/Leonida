import { defineConfig, devices } from '@playwright/test'

/**
 * Browser-Tests gegen ein echtes Backend mit eigener Datenbank (leonida_e2e, wird bei jedem Lauf neu befüllt).
 * Start: `npm run e2e` (baut das Frontend und startet scripts/e2e-server.sh auf Port 8010).
 * Browser: `npx playwright install chromium` oder E2E_CHROME=/Pfad/zu/chrome.
 */
const chrome = process.env.E2E_CHROME

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  reporter: [['list']],
  use: {
    ...devices['Desktop Chrome'],
    baseURL: 'http://127.0.0.1:8010',
    locale: 'de-DE',
    viewport: { width: 1440, height: 900 },
    trace: 'retain-on-failure',
    launchOptions: chrome ? { executablePath: chrome, args: ['--no-sandbox'] } : {},
  },
  webServer: {
    command: 'npm run build && bash ../scripts/e2e-server.sh',
    url: 'http://127.0.0.1:8010/up',
    timeout: 120_000,
    reuseExistingServer: false,
    stdout: 'ignore',
  },
})

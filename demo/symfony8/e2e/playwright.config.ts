import { defineConfig, devices } from '@playwright/test';

/**
 * REQ-DEMO-013 — Playwright e2e + screenshots for the PdfSignable Symfony 8 demo.
 *
 * The demo is served over HTTPS only (FrankenPHP/Caddy `tls internal`, local dev CA), so the
 * Makefile runs the Playwright container inside the demo php container's network namespace
 * (`--network container:pdf-signable-symfony8`) and targets https://localhost.
 * Override with DEMO_BASE_URL when running elsewhere.
 */
const baseURL = process.env.DEMO_BASE_URL || 'https://localhost';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  timeout: 90_000,
  reporter: [['list']],
  use: {
    baseURL,
    // Caddy `tls internal` certificate is signed by a local CA the browser does not trust.
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    viewport: { width: 1280, height: 900 },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
      testMatch: /.*\.spec\.ts/,
      testIgnore: /screenshots\.spec\.ts/,
    },
    {
      name: 'screenshots',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } },
      testMatch: /screenshots\.spec\.ts/,
    },
  ],
});

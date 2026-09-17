import { defineConfig, devices } from '@playwright/test';

/**
 * Config Playwright (E2E web, Lot 0).
 * - `E2E_BASE_URL` : URL de l'app Symfony (defaut http://127.0.0.1:8000).
 * - `MAILPIT_API`  : API Mailpit pour lire les e-mails (defaut http://127.0.0.1:8025).
 * Le parcours d'auth tourne sur Chromium ; un smoke responsive verifie desktop + mobile.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 45_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  // Aucun réessai, CI comprise. `retries: 1` a gardé la CI verte pendant des semaines en rejouant
  // les tests que la course entre un rechargement et sa poussée temps réel faisait échouer — un
  // défaut de production, pas un aléa de test (journal 2026-09-18). Un échec E2E doit se voir.
  retries: 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
    // `on-first-retry` ne produisait plus rien sans réessai : la trace se garde sur échec.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    {
      name: 'mobile',
      testMatch: /responsive\.spec\.ts/,
      use: { ...devices['Pixel 7'] },
    },
  ],
});

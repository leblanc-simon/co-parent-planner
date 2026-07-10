import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';

const PASSWORD = 'MotDePasseTresLong123';

/** Inscrit → vérifie (Mailpit) → connecte → crée une famille → laisse la page sur l'accueil. */
async function signUpLoginWithFamily(
  page: Page,
  request: import('@playwright/test').APIRequestContext,
  email: string,
): Promise<void> {
  await page.goto('/register');
  await page.fill('#fullName', 'Alice PWA');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  const token = await waitForVerificationToken(request, email);
  await page.goto(`/verify-email?token=${encodeURIComponent(token)}`);
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  await page.goto('/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/onboarding', { timeout: 15_000 });

  await page.fill('#name', 'Famille PWA E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });
}

test('PWA : manifest localisé + service worker installables et enregistrés', async ({
  page,
  request,
}) => {
  // Manifest servi et valide (robuste, sans SW).
  const manifest = await request.get('/manifest.webmanifest');
  expect(manifest.ok()).toBeTruthy();
  expect(manifest.headers()['content-type']).toContain('application/manifest+json');
  const mj = await manifest.json();
  expect(mj.display).toBe('standalone');
  expect(mj.name).toContain('CoParent');
  expect(mj.icons.length).toBeGreaterThanOrEqual(2);

  // Service worker servi à la racine du scope (en-tête Service-Worker-Allowed).
  const sw = await request.get('/sw.js');
  expect(sw.ok()).toBeTruthy();
  expect(sw.headers()['service-worker-allowed']).toBe('/');

  // Enregistrement effectif du SW dans le navigateur, sur une page publique.
  await page.goto('/login');
  await expect(page.locator('link[rel="manifest"]')).toHaveCount(1);
  await page.waitForFunction(
    async () => {
      const reg = await navigator.serviceWorker?.getRegistration();
      return !!(reg && (reg.active || reg.installing || reg.waiting));
    },
    null,
    { timeout: 20_000 },
  );
});

test('PWA : hors-ligne — écran RO depuis le cache + bannière, mutation bloquée, reconnexion', async ({
  page,
  request,
  context,
}) => {
  await signUpLoginWithFamily(page, request, `e2e-pwa-${Date.now()}@example.test`);

  // Prise de contrôle du SW puis mise en cache de l'écran Agenda (network-first).
  await page.goto('/');
  await page.waitForFunction(() => navigator.serviceWorker?.controller != null, null, {
    timeout: 20_000,
  });
  await page.goto('/agenda');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('h1')).toContainText('Agenda');

  // ---- Hors-ligne ----
  await context.setOffline(true);
  await page.goto('/agenda'); // servi par le SW (cache de l'écran, sinon repli page hors-ligne)
  // Une page applicative est rendue (pas d'erreur navigateur brute) et la bannière hors-ligne s'affiche.
  await expect(page).toHaveTitle(/CoParent/);
  await expect(page.locator('.pwa-banner').first()).toContainText('Hors-ligne');

  // Mutation hors-ligne : bloquée par le SW (réponse synthétique 503), jamais mise en file (§4.3).
  const status = await page.evaluate(async () => {
    const r = await fetch('/api/families', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{}',
    });
    return r.status;
  });
  expect(status).toBe(503);

  // ---- Reconnexion ----
  await context.setOffline(false);
  await page.goto('/agenda');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('.pwa-banner').first()).toBeHidden();
});

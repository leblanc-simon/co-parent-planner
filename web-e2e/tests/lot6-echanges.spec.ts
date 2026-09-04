import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';
import { waitForSaved } from '../helpers/page';

const PASSWORD = 'MotDePasseTresLong123';

/** Inscrit, vérifie (Mailpit) et connecte un utilisateur ; laisse la page sur /onboarding. */
async function signUpAndLogin(
  page: Page,
  request: import('@playwright/test').APIRequestContext,
  email: string,
  fullName: string,
): Promise<void> {
  await page.goto('/register');
  await page.fill('#fullName', fullName);
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
}

/** Ouvre le panneau de création (replié dans un <details>). */
async function openCreate(page: Page): Promise<void> {
  await page.locator('.create-panel').first().evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
}

function pastDate(days: number): string {
  const d = new Date(Date.now() - days * 86_400_000);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test('Échanges : discussion + message, décision + bascule, marqueur « en retard »', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-ex-${stamp}@example.test`, 'Alice Échanges');

  await page.fill('#name', 'Famille Échanges E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  await page.goto('/echanges');
  await expect(page.locator('h1')).toContainText('Échanges');
  await expect(page.locator('.exchanges__list')).toContainText('Aucun échange pour le moment');

  // ---- Créer une discussion ----
  await openCreate(page);
  await page.selectOption('#e-type', 'discussion');
  await page.fill('#e-title', 'Organisation vacances');
  await page.locator('.exchange-form button[type="submit"]').click();
  await waitForSaved(page);
  const disc = page.locator('.exchange-card').filter({ hasText: 'Organisation vacances' });
  await expect(disc).toHaveCount(1);

  // ---- Ouvrir + poster un message ----
  await disc.first().click();
  await waitForSaved(page);
  await page.fill('.note-form textarea', 'On part en juillet ?');
  await page.locator('.note-form button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.exchange-thread')).toContainText('On part en juillet ?');

  // ---- Créer une décision puis valider (électorat = 1 → bascule immédiate) ----
  await page.goto('/echanges');
  await openCreate(page);
  await page.selectOption('#e-type', 'decision');
  await page.fill('#e-title', 'Choix du centre aéré');
  const labels = page.locator('[name="optionLabel[]"]');
  await labels.nth(0).fill('Centre A');
  await labels.nth(1).fill('Centre B');
  await page.locator('.exchange-form button[type="submit"]').click();
  await waitForSaved(page);

  await page.locator('.exchange-card').filter({ hasText: 'Choix du centre aéré' }).first().click();
  await waitForSaved(page);
  await page.locator('button[data-action="exchanges#decide"]').first().click();
  await waitForSaved(page);
  await expect(page.locator('.exchange-decided-banner')).toBeVisible();

  // ---- Marqueur « en retard » : discussion ouverte à échéance passée ----
  await page.goto('/echanges');
  await openCreate(page);
  await page.selectOption('#e-type', 'discussion');
  await page.fill('#e-title', 'Sujet en retard');
  await page.fill('#e-deadline', pastDate(5));
  await page.locator('.exchange-form button[type="submit"]').click();
  await waitForSaved(page);
  const late = page.locator('.exchange-card').filter({ hasText: 'Sujet en retard' });
  await expect(late.locator('.exchange-tag--overdue')).toBeVisible();
});

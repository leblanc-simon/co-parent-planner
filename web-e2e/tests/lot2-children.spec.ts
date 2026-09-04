import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';
import { waitForSaved } from '../helpers/page';

const PASSWORD = 'MotDePasseTresLong123';

async function signUpLoginAndCreateFamily(
  page: Page,
  request: import('@playwright/test').APIRequestContext,
  email: string,
): Promise<void> {
  await page.goto('/register');
  await page.fill('#fullName', 'Carla Parent');
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

  await page.fill('#name', 'Famille Enfants E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });
}

test('Enfants : créer un profil, bandeau anti-santé, ajouter une note', async ({
  page,
  request,
}) => {
  await signUpLoginAndCreateFamily(page, request, `e2e-child-${Date.now()}@example.test`);

  // Écran Enfants.
  await page.goto('/children');
  await expect(page.locator('h1')).toContainText('Enfants');

  // Créer un enfant via le volet « Ajouter un enfant ».
  await page.locator('.create-panel summary').click();
  await page.fill('#nc-name', 'Léa');
  await page.fill('#nc-birth', '2016-09-12');
  await page.locator('.create-panel__form button[type="submit"]').click();
  await waitForSaved(page);

  // La fiche est sélectionnée : bandeau anti-données-de-santé visible.
  await expect(page.locator('.health-banner')).toBeVisible();
  await expect(page.locator('.child-head h2')).toContainText('Léa');

  // Ajouter une note → elle apparaît dans la liste.
  await page.fill('.note-form textarea', 'RDV de rentrée pris');
  await page.locator('.note-form button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.notes')).toContainText('RDV de rentrée pris');

  // Bloc Accès & droits : le parent admin y figure en Édition.
  await expect(page.locator('.access-list')).toContainText('Édition');
});

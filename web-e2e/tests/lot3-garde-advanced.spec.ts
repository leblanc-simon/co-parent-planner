import { Browser, Page, expect, test } from '@playwright/test';

import {
  waitForInvitationToken,
  waitForVerificationToken,
} from '../helpers/mailpit';

const PASSWORD = 'MotDePasseTresLong123';

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

/** Famille séparée avec deux parents (A connecté sur `page`), positionnée sur /garde. */
async function setupTwoParentFamily(
  page: Page,
  request: import('@playwright/test').APIRequestContext,
  browser: Browser,
  stamp: number,
): Promise<void> {
  await signUpAndLogin(page, request, `e2e-adv-a-${stamp}@example.test`, 'Alice Adv');
  await page.fill('#name', 'Famille Garde Adv');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  const parentBEmail = `e2e-adv-b-${stamp}@example.test`;
  await page.goto('/settings');
  await page.fill('#i-email', parentBEmail);
  await page.selectOption('#i-role', 'parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  const inviteToken = await waitForInvitationToken(request, parentBEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, parentBEmail, 'Bob Adv');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.waitForURL('**/settings', { timeout: 15_000 });
  await contextB.close();
}

test('Garde avancée : rythme custom 14 jours, vacances manuelle + règle, exception supprimée', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  await setupTwoParentFamily(page, request, browser, Date.now());
  await page.goto('/garde');

  // ---- Rythme personnalisé (14 nuits) : la grille custom apparaît et se sauvegarde ----
  await page.selectOption('#g-kind', 'custom');
  await page.fill('#g-anchor', '2026-06-21');
  await page.selectOption('#g-pa', { label: 'Alice Adv' });
  await page.selectOption('#g-pb', { label: 'Bob Adv' });
  const customSelects = page.locator('select[name="custom[]"]');
  await expect(customSelects).toHaveCount(14);
  // Motif : 1re semaine A, 2de semaine B → les deux couleurs apparaissent.
  for (let i = 7; i < 14; i++) {
    await customSelects.nth(i).selectOption('B');
  }
  await page.locator('.garde-config button[type="submit"]').click();

  await expect(page.locator('.garde-cal')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('.garde-day--a').first()).toBeVisible();
  await expect(page.locator('.garde-day--b').first()).toBeVisible();

  // ---- Vacances saisie manuellement (pas d'import réseau) ----
  const holidayForm = page.locator('form:has(input[name="label"])');
  await holidayForm.locator('[name="label"]').fill('Vacances test');
  await holidayForm.locator('[name="startDate"]').fill('2026-07-13');
  await holidayForm.locator('[name="endDate"]').fill('2026-07-20');
  await holidayForm.locator('button[type="submit"]').click();
  await expect(page.locator('.garde-list')).toContainText('Vacances test', { timeout: 15_000 });

  // ---- Règle « fixed_parent » (parent A) affectée à cette période ----
  const ruleForm = page.locator('.garde-rule-form').first();
  await ruleForm.locator('[name="kind"]').selectOption('fixed_parent');
  await ruleForm.locator('[name="parent"]').selectOption('A');
  await ruleForm.locator('button[type="submit"]').click();
  await expect(page.locator('.garde-list')).toContainText('fixed_parent', { timeout: 15_000 });

  // ---- Exception ajoutée puis supprimée via la liste ----
  const excForm = page.locator('form:has(input[name="reason"])');
  await excForm.locator('[name="startDate"]').fill('2026-07-05');
  await excForm.locator('[name="endDate"]').fill('2026-07-05');
  await excForm.locator('[name="reason"]').fill('Rendez-vous');
  await excForm.locator('button[type="submit"]').click();
  // Deux listes .garde-list (exceptions + vacances) : cibler l'entrée précise, pas la classe.
  await expect(
    page.locator('.garde-list li', { hasText: 'Rendez-vous' }),
  ).toBeVisible({ timeout: 15_000 });

  // Supprimer l'exception (bouton dans son <li>).
  await page
    .locator('.garde-list li', { hasText: 'Rendez-vous' })
    .locator('button', { hasText: 'Supprimer' })
    .click();
  await expect(
    page.locator('.garde-list li', { hasText: 'Rendez-vous' }),
  ).toHaveCount(0, { timeout: 15_000 });
});

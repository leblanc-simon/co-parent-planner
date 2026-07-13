import { Browser, Page, expect, test } from '@playwright/test';

import {
  waitForInvitationToken,
  waitForVerificationToken,
} from '../helpers/mailpit';

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

test('Garde : configurer un rythme → calendrier coloré → échanger un jour', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  const stamp = Date.now();
  const adminEmail = `e2e-garde-a-${stamp}@example.test`;
  const parentBEmail = `e2e-garde-b-${stamp}@example.test`;

  // ---- Parent A : compte + famille séparée ----
  await signUpAndLogin(page, request, adminEmail, 'Alice Garde');
  await page.fill('#name', 'Famille Garde E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), {
    timeout: 15_000,
  });

  // ---- Invitation d'un second parent (le rythme exige deux parents distincts) ----
  await page.goto('/settings');
  await page.fill('#i-email', parentBEmail);
  await page.selectOption('#i-role', 'parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('.invitations')).toContainText(parentBEmail);

  // ---- Parent B accepte l'invitation ----
  const inviteToken = await waitForInvitationToken(request, parentBEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, parentBEmail, 'Bob Garde');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.click('button[type="submit"]');
  await pageB.waitForURL('**/invitations/accepted', { timeout: 15_000 });
  await contextB.close();

  // ---- Parent A : écran Garde, sans rythme encore configuré ----
  await page.goto('/garde');
  await expect(page.locator('h1')).toContainText('Planning de garde');
  await expect(page.locator('.empty-state')).toContainText('Aucun rythme de garde configuré');

  // ---- Configurer un rythme « une semaine sur deux » avec deux parents distincts ----
  await page.selectOption('#g-kind', 'alternating');
  await page.fill('#g-anchor', '2026-06-21');
  await page.selectOption('#g-weekday', '0'); // dimanche
  await page.selectOption('#g-pa', { label: 'Alice Garde' });
  await page.selectOption('#g-pb', { label: 'Bob Garde' });
  await page.locator('.garde-config button[type="submit"]').click();

  // La génération est synchrone en E2E (Messenger sync://) → après rechargement, calendrier peuplé.
  await expect(page.locator('.garde-cal')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('.garde-day--a').first()).toBeVisible();
  await expect(page.locator('.garde-day--b').first()).toBeVisible();
  // Les statistiques du mois sont calculées sur les jours effectifs.
  // Tuiles de stats colorées A/B (WG-02) — libellées avec les noms réels des parents + %.
  await expect(page.locator('.garde-stat--a')).toBeVisible();
  await expect(page.locator('.garde-stat--b')).toBeVisible();

  // ---- Échanger un jour : clic sur une case → exception d'un jour ----
  await page.locator('.garde-day[role="button"]').first().click();
  await expect(page.locator('.garde-day--exception').first()).toBeVisible({ timeout: 15_000 });
  // L'exception « Échange » apparaît dans la liste.
  await expect(page.locator('.garde-list')).toContainText('Échange');
});

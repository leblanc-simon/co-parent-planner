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

async function createFamily(page: Page, name: string): Promise<void> {
  await page.fill('#name', name);
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });
}

test('membre : inviter → accepter → retirer de la famille', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  const stamp = Date.now();
  const inviteeEmail = `e2e-rm-invitee-${stamp}@example.test`;

  await signUpAndLogin(page, request, `e2e-rm-admin-${stamp}@example.test`, 'Admin Rm');
  await createFamily(page, 'Famille Retrait');

  await page.goto('/settings');
  await page.fill('#i-email', inviteeEmail);
  await page.selectOption('#i-role', 'step_parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  const inviteToken = await waitForInvitationToken(request, inviteeEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, inviteeEmail, 'Bob Invitee');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.waitForURL('**/settings', { timeout: 15_000 });
  await contextB.close();

  // L'admin voit le membre, puis le retire (confirm() accepté).
  await page.goto('/settings');
  await expect(page.locator('.members')).toContainText(inviteeEmail);
  page.on('dialog', (d) => d.accept());
  await page
    .locator('.member-row', { hasText: inviteeEmail })
    .locator('button', { hasText: 'Retirer' })
    .click();
  await page.waitForLoadState('networkidle');

  await page.goto('/settings');
  await expect(page.locator('.members')).not.toContainText(inviteeEmail);
});

test('dernier admin protégé : la démotion du seul admin est refusée', async ({
  page,
  request,
}) => {
  await signUpAndLogin(page, request, `e2e-lastadmin-${Date.now()}@example.test`, 'Solo Admin');
  await createFamily(page, 'Famille Solo');

  await page.goto('/settings');
  const selfRow = page.locator('.member-row').first();
  const adminCheckbox = selfRow.locator('input[name="isAdmin"]');
  await expect(adminCheckbox).toBeChecked();

  // Tenter de se retirer le rôle admin (seul admin) → le serveur (autorité) refuse.
  await adminCheckbox.uncheck();
  await selfRow.locator('button', { hasText: 'Enregistrer' }).click();
  await expect(page.locator('[data-settings-target="error"]')).toBeVisible({ timeout: 15_000 });

  // Après rechargement, l'utilisateur est toujours admin (invariant préservé).
  await page.goto('/settings');
  await expect(page.locator('.member-row').first().locator('input[name="isAdmin"]')).toBeChecked();
});

test('bascule de famille active : X-Family-Id suit la famille choisie', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  const stamp = Date.now();
  const aEmail = `e2e-switch-a-${stamp}@example.test`;

  // A crée la famille Alpha.
  await signUpAndLogin(page, request, aEmail, 'Anna Switch');
  await createFamily(page, 'Alpha E2E');

  // B crée Beta et invite A comme parent.
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, `e2e-switch-b-${stamp}@example.test`, 'Bruno Switch');
  await createFamily(pageB, 'Beta E2E');
  await pageB.goto('/settings');
  await pageB.fill('#i-email', aEmail);
  await pageB.selectOption('#i-role', 'parent');
  await pageB.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await pageB.waitForLoadState('networkidle');
  const inviteToken = await waitForInvitationToken(request, aEmail);
  await contextB.close();

  // A accepte → membre de Alpha ET Beta.
  await page.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await page.waitForURL('**/settings', { timeout: 15_000 });

  // A bascule sur Beta via le sélecteur de famille.
  await page.locator('.family-select__button').click();
  await page.locator('.family-select__item', { hasText: 'Beta E2E' }).click();
  await page.waitForLoadState('networkidle');

  // La famille active est bien Beta (en-tête + champ nom de la famille).
  await expect(page.locator('.family-select__button')).toContainText('Beta E2E');
  await expect(page.locator('#f-name')).toHaveValue('Beta E2E');
});

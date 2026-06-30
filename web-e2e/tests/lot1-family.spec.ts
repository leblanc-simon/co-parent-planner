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

test('créer une famille → inviter → accepter → vérifier accès → ajuster droits', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  const stamp = Date.now();
  const adminEmail = `e2e-admin-${stamp}@example.test`;
  const inviteeEmail = `e2e-invitee-${stamp}@example.test`;

  // ---- Parent A : compte + première famille (US-ONB-1a) ----
  await signUpAndLogin(page, request, adminEmail, 'Alice Admin');
  await page.fill('#name', 'Famille E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), {
    timeout: 15_000,
  });

  // ---- Réglages famille (US-SET-2a) : zone scolaire persistée ----
  await page.goto('/settings');
  await expect(page.locator('h1')).toContainText('Compte');
  await page.selectOption('#f-zone', 'C');
  await page.locator('[data-settings-target="familyForm"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await page.goto('/settings');
  await expect(page.locator('#f-zone')).toHaveValue('C');

  // ---- Invitation d'un beau-parent (US-INV-1) ----
  await page.fill('#i-email', inviteeEmail);
  await page.selectOption('#i-role', 'step_parent');
  await page
    .locator('[data-settings-target="inviteRights"] select[name="rights[agenda]"]')
    .selectOption('view');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('.invitations')).toContainText(inviteeEmail);

  // ---- Parent B : accepte l'invitation reçue par e-mail (US-INV-3) ----
  const inviteToken = await waitForInvitationToken(request, inviteeEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, inviteeEmail, 'Bob Beau-parent');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.waitForURL('**/settings', { timeout: 15_000 });
  // B voit bien la famille rejointe.
  await expect(pageB.locator('h1')).toContainText('Compte');

  // ---- A voit B dans la liste des membres (vérification de l'accès) ----
  await page.goto('/settings');
  await expect(page.locator('.members')).toContainText(inviteeEmail);

  // ---- A ajuste les droits du beau-parent : agenda -> edit ----
  const memberForm = page.locator('.member-row', { hasText: inviteeEmail });
  await memberForm.locator('select[name="rights[agenda]"]').selectOption('edit');
  await memberForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await page.goto('/settings');
  const memberFormReloaded = page.locator('.member-row', { hasText: inviteeEmail });
  await expect(
    memberFormReloaded.locator('select[name="rights[agenda]"]'),
  ).toHaveValue('edit');

  await contextB.close();
});

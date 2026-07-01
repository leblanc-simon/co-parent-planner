import { expect, test } from '@playwright/test';

import { waitForResetToken, waitForVerificationToken } from '../helpers/mailpit';

const PASSWORD = 'MotDePasseTresLong123';
const NEW_PASSWORD = 'NouveauMotDePasse456';

test('mot de passe oublié : demande → e-mail Mailpit → réinitialisation → connexion', async ({
  page,
  request,
}) => {
  const email = `e2e-reset-${Date.now()}@example.test`;

  // ---- Compte créé + vérifié (pour pouvoir se connecter ensuite) ----
  await page.goto('/register');
  await page.fill('#fullName', 'Rita Reset');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  const verifyToken = await waitForVerificationToken(request, email);
  await page.goto(`/verify-email?token=${encodeURIComponent(verifyToken)}`);
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  // ---- Demande de réinitialisation (US-AUTH : non énumérant, notice affichée) ----
  await page.goto('/forgot-password');
  await page.fill('#email', email);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  // ---- Lien reçu par e-mail → page de réinitialisation → nouveau mot de passe ----
  const resetToken = await waitForResetToken(request, email);
  await page.goto(`/reset-password?token=${encodeURIComponent(resetToken)}`);
  await page.fill('#password', NEW_PASSWORD);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  // ---- L'ancien mot de passe ne fonctionne plus ----
  await page.goto('/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="error"]')).toBeVisible();

  // ---- Le nouveau mot de passe permet de se connecter (→ onboarding, pas de famille) ----
  await page.goto('/login');
  await page.fill('#email', email);
  await page.fill('#password', NEW_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/onboarding', { timeout: 15_000 });
});

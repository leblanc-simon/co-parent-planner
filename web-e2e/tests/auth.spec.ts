import { expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';

/**
 * Parcours nominal du Lot 0 (DoD §8.2) : inscription -> verification e-mail via
 * Mailpit -> connexion. Touche le web (Twig/Turbo/Stimulus), l'API d'auth et le
 * worker Messenger (e-mail asynchrone).
 */
test('inscription -> verification e-mail (Mailpit) -> connexion', async ({
  page,
  request,
}) => {
  const email = `e2e-${Date.now()}@example.test`;
  const password = 'MotDePasseTresLong123';

  // 1. Inscription (US-AUTH-1) : reponse anti-enumerante, aucun token (D1).
  await page.goto('/register');
  await page.fill('#fullName', 'Alice E2E');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();

  // 2. Verification (US-AUTH-1 sc.5) : on lit le lien recu dans Mailpit.
  const token = await waitForVerificationToken(request, email);
  await page.goto(`/verify-email?token=${encodeURIComponent(token)}`);
  // La page auto-soumet POST /api/auth/verify-email : on attend le succes.
  await expect(page.locator('[data-auth-form-target="notice"]')).toBeVisible();
  await expect(page.locator('[data-auth-form-target="error"]')).toHaveText('');

  // 3. Connexion (US-AUTH-2 sc.1) : compte desormais actif.
  await page.goto('/login');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');

  // Authentifie sans famille -> onboarding obligatoire (US-ONB-1 sc.6).
  await page.waitForURL('**/onboarding', { timeout: 15_000 });
  await expect(page).toHaveURL(/onboarding/);
});

test('connexion refusee avec des identifiants invalides (non enumerant)', async ({
  page,
}) => {
  await page.goto('/login');
  await page.fill('#email', 'inconnu@example.test');
  await page.fill('#password', 'MauvaisMotDePasse1');
  await page.click('button[type="submit"]');
  await expect(page.locator('[data-auth-form-target="error"]')).not.toHaveText(
    '',
  );
  await expect(page).toHaveURL(/login/);
});

import { Browser, Page, expect, test } from '@playwright/test';

import { waitForInvitationToken, waitForVerificationToken } from '../helpers/mailpit';
import { waitForSaved } from '../helpers/page';

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

/**
 * Parcours complet du module Dépenses (Lot 10) : activation par l'administrateur, saisie, solde,
 * règlement, rappel et suppression.
 *
 * Le second parent est indispensable et non décoratif : sans lui, toute dépense est intégralement à
 * la charge de son payeur, le solde reste trivialement équilibré et le rappel n'a aucun destinataire
 * — le test passerait sans rien prouver.
 */
test('Dépenses : activer le module, saisir, solder, relancer, supprimer', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  // Suppression = window.confirm ; Playwright rejette les dialogues par défaut.
  page.on('dialog', (d) => d.accept());
  const stamp = Date.now();

  // ---- Famille séparée, deux parents ----
  await signUpAndLogin(page, request, `e2e-dep-a-${stamp}@example.test`, 'Alice Dep');
  await page.fill('#name', 'Famille Dépenses E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  const parentBEmail = `e2e-dep-b-${stamp}@example.test`;
  await page.goto('/settings?tab=famille');
  await page.fill('#i-email', parentBEmail);
  await page.selectOption('#i-role', 'parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await waitForSaved(page);

  const inviteToken = await waitForInvitationToken(request, parentBEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, parentBEmail, 'Bob Dep');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.click('button[type="submit"]');
  await pageB.waitForURL('**/invitations/accepted', { timeout: 15_000 });
  await contextB.close();

  // ---- Un enfant : une dépense doit concerner au moins un enfant ----
  await page.goto('/children');
  await page.locator('.create-panel summary').click();
  await page.fill('#nc-name', 'Léa');
  await page.fill('#nc-birth', '2016-09-12');
  await page.locator('.create-panel__form button[type="submit"]').click();
  await waitForSaved(page);

  // ---- Module inactif : ni onglet, ni écran ----
  await page.goto('/');
  await expect(page.locator('.sidebar__nav a[href="/depenses"]')).toHaveCount(0);

  // ---- Activation par l'administrateur (US-DEP-6) ----
  await page.goto('/settings?tab=famille');
  // Clic sur la carte, pas sur la case : l'interrupteur iOS du design system rend l'`input` en
  // `opacity:0` sous son curseur, qui intercepte les clics. C'est le geste réel de l'utilisateur —
  // la case reste atteignable au clavier et bascule normalement (le `<label>` l'englobe).
  const moduleToggle = page.locator('.overlay-card', { hasText: 'Partage des dépenses' });
  await moduleToggle.click();
  await expect(moduleToggle.locator('input[name="expensesEnabled"]')).toBeChecked();
  await page.locator('[data-settings-target="familyForm"] button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.sidebar__nav a[href="/depenses"]')).toBeVisible();

  // ---- Écran Dépenses, vide ----
  await page.goto('/depenses');
  await expect(page.locator('h1')).toContainText('Dépenses partagées');
  await expect(page.locator('.dep-balance')).toContainText('Comptes équilibrés');

  // ---- Saisie, partage 50/50 par défaut ----
  await page.locator('.create-panel').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await page.fill('#d-title', 'Cantine septembre');
  await page.selectOption('#d-cat', 'scolarite');
  await page.fill('#d-amount', '42.50');
  await page.check('input[name="childIds[]"]');
  await page.locator('.agenda-form button[type="submit"]').click();
  await waitForSaved(page);

  await expect(page.locator('.dep-item__title')).toContainText('Cantine septembre');
  // 42,50 € avancés par Alice, partagés en deux : Bob lui doit 21,25 €.
  await expect(page.locator('.dep-balance')).toContainText('21,25');
  await expect(page.locator('.dep-balance')).toContainText('Bob Dep doit');
  await expect(page.locator('.dep-cats')).toContainText('42,50');
  await expect(page.locator('.dep-status')).toContainText('À régler');

  // ---- Partage personnalisé (70 / 30) ----
  await page.locator('.create-panel').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await page.locator('.create-panel details').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await page.fill('#d-title', 'Manteau d’hiver');
  await page.selectOption('#d-cat', 'vetements');
  await page.fill('#d-amount', '100');
  await page.check('input[name="childIds[]"]');
  const shares = page.locator('input[name="sharePercent[]"]');
  await shares.nth(0).fill('70');
  await shares.nth(1).fill('30');
  await page.locator('.agenda-form button[type="submit"]').click();
  await waitForSaved(page);

  await expect(page.locator('.dep-list')).toContainText('Manteau d’hiver');
  // Alice a tout avancé : Bob lui doit 21,25 € de cantine + 30,00 € de manteau.
  await expect(page.locator('.dep-balance')).toContainText('51,25');

  // ---- Filtre par catégorie ----
  await page.goto('/depenses?category=vetements');
  await expect(page.locator('.dep-item')).toHaveCount(1);
  await expect(page.locator('.dep-list')).toContainText('Manteau');
  // Le solde, lui, ne suit PAS le filtre : il porte toujours sur toute la famille.
  await expect(page.locator('.dep-balance')).toContainText('51,25');

  // ---- Rappel au débiteur, puis cooldown ----
  await page.goto('/depenses');
  await page.locator('.dep-item', { hasText: 'Cantine' }).getByRole('button', { name: 'Relancer' }).click();
  await waitForSaved(page);
  await expect(
    page.locator('.dep-item', { hasText: 'Cantine' }).getByRole('button', { name: 'Relancer' }),
  ).toBeDisabled();

  // ---- Marquer réglée : la dépense sort du solde, pas du total engagé ----
  await page.locator('.dep-item', { hasText: 'Cantine' }).getByRole('button', { name: 'Marquer réglée' }).click();
  await waitForSaved(page);
  await expect(page.locator('.dep-item', { hasText: 'Cantine' }).locator('.dep-status')).toContainText('Réglée');
  await expect(page.locator('.dep-balance')).toContainText('30,00');
  await expect(page.locator('.dep-cats')).toContainText('142,50');

  // ---- Suppression ----
  await page.locator('.dep-item', { hasText: 'Manteau' }).getByRole('button', { name: 'Supprimer' }).click();
  await waitForSaved(page);
  await expect(page.locator('.dep-list')).not.toContainText('Manteau');
  await expect(page.locator('.dep-balance')).toContainText('Comptes équilibrés');
});

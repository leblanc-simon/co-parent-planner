import { Browser, Page, expect, test } from '@playwright/test';

import {
  waitForInvitationToken,
  waitForVerificationToken,
} from '../helpers/mailpit';
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
  await waitForSaved(page);
  await page.goto('/settings');
  await expect(page.locator('#f-zone')).toHaveValue('C');

  // ---- Invitation d'un beau-parent (US-INV-1) ----
  await page.fill('#i-email', inviteeEmail);
  await page.selectOption('#i-role', 'step_parent');
  await page
    .locator('[data-settings-target="inviteRights"] select[name="rights[agenda]"]')
    .selectOption('view');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.invitations')).toContainText(inviteeEmail);

  // ---- Parent B : accepte l'invitation reçue par e-mail (US-INV-3) ----
  const inviteToken = await waitForInvitationToken(request, inviteeEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, inviteeEmail, 'Bob Beau-parent');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  // Déjà connecté avec l'e-mail invité : confirmation explicite (rejoindre est un acte volontaire).
  await expect(pageB.locator('.auth__title')).toContainText('Rejoindre Famille E2E');
  await pageB.click('button[type="submit"]');
  // Arrivée sur l'écran de bienvenue (connecté).
  await pageB.waitForURL('**/invitations/accepted', { timeout: 15_000 });
  await expect(pageB.locator('.auth__title')).toContainText('Bienvenue');

  // ---- A voit B dans la liste des membres (vérification de l'accès) ----
  await page.goto('/settings');
  await expect(page.locator('.members')).toContainText(inviteeEmail);

  // ---- A ajuste les droits du beau-parent : agenda -> edit ----
  // L'édition des droits est repliée dans un <details> (ligne compacte par défaut, WP-04).
  const memberForm = page.locator('.member-row', { hasText: inviteeEmail });
  await memberForm.locator('details.member-row__edit').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await memberForm.locator('select[name="rights[agenda]"]').selectOption('edit');
  await memberForm.locator('button[type="submit"]').click();
  await waitForSaved(page);
  await page.goto('/settings');
  const memberFormReloaded = page.locator('.member-row', { hasText: inviteeEmail });
  await expect(
    memberFormReloaded.locator('select[name="rights[agenda]"]'),
  ).toHaveValue('edit');

  await contextB.close();
});

test('lien d\'invitation (non connecté) → création de compte contextualisée → acceptation', async ({
  page,
  request,
  browser,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
  browser: Browser;
}) => {
  const stamp = Date.now();
  const adminEmail = `e2e-admin2-${stamp}@example.test`;
  const inviteeEmail = `e2e-join-${stamp}@example.test`;

  // ---- Parent A : compte + famille + invitation ----
  await signUpAndLogin(page, request, adminEmail, 'Alice Admin');
  await page.fill('#name', 'Famille Join');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), {
    timeout: 15_000,
  });
  await page.goto('/settings');
  await page.fill('#i-email', inviteeEmail);
  await page.selectOption('#i-role', 'step_parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.invitations')).toContainText(inviteeEmail);

  // ---- Parent B : ouvre le lien SANS être connecté → formulaire de création de compte ----
  const inviteToken = await waitForInvitationToken(request, inviteeEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();

  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  // Contexte affiché : nom de la famille + e-mail nominatif pré-rempli (readonly), pas de redirection login.
  await expect(pageB.locator('.auth__title')).toContainText('Rejoindre Famille Join');
  await expect(pageB.locator('#email')).toHaveValue(inviteeEmail);
  await expect(pageB.locator('#email')).toHaveAttribute('readonly', '');

  // Flux simplifié : UN seul écran (mot de passe) → création de compte ET acceptation en un POST,
  // arrivée CONNECTÉ. Ni second e-mail de vérification, ni reconnexion.
  await pageB.fill('#fullName', 'Bob Beau-parent');
  await pageB.fill('#password', PASSWORD);
  await pageB.click('button[type="submit"]');
  await pageB.waitForURL('**/invitations/accepted', { timeout: 20_000 });
  await expect(pageB.locator('.auth__title')).toContainText('Bienvenue');

  // B est bien connecté : l'accueil ne renvoie pas vers /login.
  await pageB.goto('/');
  await expect(pageB).not.toHaveURL(/\/login$/);

  // ---- A voit B parmi les membres ----
  await page.goto('/settings');
  await expect(page.locator('.members')).toContainText(inviteeEmail);

  await contextB.close();
});

/**
 * Vague 2 de la revue web (constats 6.10 et 6.6) : deux parcours contractualisés que rien ne
 * reliait dans l'interface.
 *
 * Ce test est un E2E parce que la partie décisive est en JavaScript — la famille créée doit
 * devenir la famille **active** (cookie `cpp_family`), sans quoi « Créer ma famille » ramenait sur
 * l'accueil de l'ancienne.
 */
test('créer une seconde famille depuis le sélecteur, et lire le code de partage', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-second-${stamp}@example.test`, 'Alice Multi');

  // Première famille (l'écran d'accueil de l'onboarding).
  await page.fill('#name', 'Foyer Un E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  // ---- 6.10 : le sélecteur de famille offre enfin la création ----
  await page.goto('/');
  await page.click('.family-select__button');
  const create = page.locator('.family-select__menu a[href="/onboarding"]');
  await expect(create).toBeVisible();
  await create.click();
  await page.waitForURL('**/onboarding', { timeout: 15_000 });

  // L'écran s'adapte : ce n'est plus une première famille.
  await expect(page.locator('.auth__title')).toContainText('Nouvelle famille');

  await page.fill('#name', 'Foyer Deux E2E');
  await page.selectOption('#type', 'intact');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  // La famille créée est devenue la famille active — sinon on retombait sur la première.
  await expect(page.locator('.family-select__label')).toContainText('Foyer Deux E2E');
  await page.click('.family-select__button');
  await expect(page.locator('.family-select__menu')).toContainText('Foyer Un E2E');

  // ---- 6.6 : le code de partage est affiché, et le formulaire le réclame ----
  await page.goto('/settings');
  const shareCode = page.locator('#l-own');
  await expect(shareCode).toBeVisible();
  await expect(shareCode).toHaveValue(/^[0-9a-f-]{36}$/);
  await expect(page.locator('label[for="l-id"]')).toContainText('Code de partage');
});

import { Browser, Page, expect, test } from '@playwright/test';

import {
  waitForInvitationToken,
  waitForVerificationToken,
} from '../helpers/mailpit';
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
  await page.goto('/settings?tab=famille');
  await page.fill('#i-email', parentBEmail);
  await page.selectOption('#i-role', 'parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await waitForSaved(page);

  const inviteToken = await waitForInvitationToken(request, parentBEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, parentBEmail, 'Bob Adv');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.click('button[type="submit"]');
  await pageB.waitForURL('**/invitations/accepted', { timeout: 15_000 });
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
  // Les actions destructives demandent désormais confirmation (window.confirm) ; Playwright
  // rejette les dialogues par défaut, on les accepte donc explicitement.
  page.on('dialog', (d) => d.accept());
  await setupTwoParentFamily(page, request, browser, Date.now());
  // L'édition du rythme, des vacances et des exceptions vit désormais dans Paramètres.
  await page.goto('/settings?tab=garde');

  // ---- Rythme personnalisé (14 nuits) : la grille de nuits cliquables apparaît et se sauvegarde ----
  await page.locator('.rhythm-card:has(input[value="custom"])').click();
  await page.fill('#g-anchor', '2026-06-21');
  await page.selectOption('#g-pa', { label: 'Alice Adv' });
  await page.selectOption('#g-pb', { label: 'Bob Adv' });
  const nights = page.locator('[data-garde-target="night"]');
  await expect(nights).toHaveCount(14);
  // Le motif par défaut alterne A/B ; on bascule une nuit d'un clic pour vérifier l'interaction,
  // le motif mixte produit les deux couleurs dans le calendrier généré.
  await nights.first().click();
  await page.locator('.garde-config button[type="submit"]').click();
  await waitForSaved(page); // succès → rechargement de /settings

  // ---- Le calendrier généré se consulte sur l'écran Garde ----
  await page.goto('/garde');
  await expect(page.locator('.garde-cal')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('.garde-day--a').first()).toBeVisible();
  await expect(page.locator('.garde-day--b').first()).toBeVisible();

  // ---- Retour dans Paramètres pour les vacances et exceptions ----
  await page.goto('/settings?tab=garde');

  // ---- Vacances saisie manuellement (pas d'import réseau) ----
  // Dates dynamiques dans le futur proche : les périodes révolues sont repliées à part et n'offrent
  // plus de formulaire de règle — une date en dur finit par y basculer et emporter le test avec.
  const isoDay = (offsetDays: number) => {
    const d = new Date(Date.now() + offsetDays * 86_400_000);
    // Composantes LOCALES : `toISOString()` bascule d'un jour dès que l'heure locale dépasse UTC.
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };
  const holidayForm = page.locator('form:has(input[name="label"])');
  await holidayForm.locator('[name="label"]').fill('Vacances test');
  await holidayForm.locator('[name="startDate"]').fill(isoDay(10));
  await holidayForm.locator('[name="endDate"]').fill(isoDay(17));
  await holidayForm.locator('button[type="submit"]').click();
  await expect(page.locator('.garde-list')).toContainText('Vacances test', { timeout: 15_000 });

  // ---- Règle « fixed_parent » (parent A) affectée à cette période ----
  // L'assertion porte sur le LIBELLÉ affiché, pas sur la valeur d'enum : l'écran exposait
  // « fixed_parent » tel quel, ce qui n'a aucun sens pour un parent qui lit son planning.
  const ruleForm = page.locator('.garde-rule-form').first();
  await ruleForm.locator('[name="kind"]').selectOption('fixed_parent');
  await ruleForm.locator('[name="parent"]').selectOption('A');
  await ruleForm.locator('button[type="submit"]').click();
  await expect(page.locator('.garde-list')).toContainText('Toujours chez le même parent', {
    timeout: 15_000,
  });
  // Le parent de départ figure désormais dans le résumé (il était invisible, et « Appliquer »
  // réinitialisait silencieusement le select sur A en régénérant la garde).
  await expect(page.locator('.garde-list')).toContainText('à partir de Alice Adv');

  // ---- Exception ajoutée puis supprimée via la liste ----
  // Date dynamique dans le futur proche : le moteur fige le passé, une date en dur finit par périmer.
  const excDate = isoDay(3);
  const excForm = page.locator('form:has(input[name="reason"])');
  await excForm.locator('[name="startDate"]').fill(excDate);
  await excForm.locator('[name="endDate"]').fill(excDate);
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

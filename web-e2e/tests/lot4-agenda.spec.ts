import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';

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

/** Date `YYYY-MM-DD` à N jours de maintenant + le mois `YYYY-MM` associé. */
function futureDate(days: number): { date: string; month: string } {
  const d = new Date(Date.now() + days * 86_400_000);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return { date: `${y}-${m}-${day}`, month: `${y}-${m}` };
}

test('Agenda : créer un événement, une série, filtrer, éditer, supprimer, « À venir »', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-agenda-${stamp}@example.test`, 'Alice Agenda');

  // ---- Famille séparée ----
  await page.fill('#name', 'Famille Agenda E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  const soon = futureDate(3);

  // ---- Écran Agenda, vide ----
  await page.goto('/agenda');
  await expect(page.locator('h1')).toContainText('Agenda');
  await expect(page.locator('.empty-state')).toContainText('Aucun événement ce mois-ci');

  // ---- Créer un événement ponctuel (toute la famille) ----
  await page.check('input[name="wholeFamily"]');
  await page.fill('#a-title', 'Cours de tennis');
  await page.selectOption('#a-cat', 'sport');
  await page.fill('#a-start', `${soon.date}T10:00`);
  await page.locator('.agenda-form button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await page.goto(`/agenda?month=${soon.month}`);
  await expect(page.locator('.agenda-events')).toContainText('Cours de tennis');
  await expect(page.locator('.agenda-event .pill--sport').first()).toBeVisible();

  // ---- Filtre par catégorie : « Musique » masque l'événement sport ----
  const tennisTiles = page.locator('.agenda-event').filter({ hasText: 'Cours de tennis' });
  await page.getByRole('link', { name: 'Musique', exact: true }).click();
  await expect(tennisTiles).toHaveCount(0);
  await page.getByRole('link', { name: 'Toutes', exact: true }).click();
  await expect(tennisTiles).toHaveCount(1);

  // ---- Éditer l'occurrence (ponctuelle) : changer le titre ----
  // On soumet le formulaire via dispatchEvent : le clic natif serait détaché par le reload Stimulus.
  const tennis = page.locator('.agenda-event').filter({ hasText: 'Cours de tennis' }).first();
  await tennis.locator('details').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await tennis.locator('input[name="title"]').fill('Tennis modifié');
  await tennis.locator('form.agenda-edit').dispatchEvent('submit');
  await page.waitForLoadState('networkidle');
  await page.goto(`/agenda?month=${soon.month}`);
  await expect(page.locator('.agenda-events')).toContainText('Tennis modifié');

  // ---- Créer une série hebdomadaire (4 occurrences) ----
  await page.check('input[name="wholeFamily"]');
  await page.fill('#a-title', 'Piano');
  await page.selectOption('#a-cat', 'musique');
  await page.fill('#a-start', `${soon.date}T17:00`);
  await page.selectOption('#a-freq', 'weekly');
  await page.fill('#a-count', '4');
  await page.locator('.agenda-form button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await page.goto(`/agenda?month=${soon.month}`);
  // Au moins une occurrence de la série dans le mois d'ancrage (la série peut couvrir plusieurs jours).
  await expect(page.locator('.agenda-event').filter({ hasText: 'Piano' }).first()).toBeVisible();

  // ---- Bloc « À venir » sur l'Accueil ----
  await page.goto('/');
  await expect(page.locator('.agenda-upcoming')).toContainText('Piano');

  // ---- Supprimer l'événement ponctuel (dispatchEvent : évite le détachement au reload) ----
  await page.goto(`/agenda?month=${soon.month}`);
  const toDelete = page.locator('.agenda-event').filter({ hasText: 'Tennis modifié' }).first();
  // Locator CSS (résout même dans un <details> replié) + dispatchEvent (déclenche deleteEvent Stimulus).
  await toDelete.locator('button', { hasText: 'Supprimer' }).dispatchEvent('click');
  await page.waitForLoadState('networkidle');
  await page.goto(`/agenda?month=${soon.month}`);
  await expect(page.locator('.agenda-event').filter({ hasText: 'Tennis modifié' })).toHaveCount(0);
});

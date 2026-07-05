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
  await page.context().clearCookies(); // repart d'une session vierge (bascule entre familles A/B)
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

/** Reconnecte un utilisateur existant (déjà rattaché à une famille) ; laisse la page hors /login. */
async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15_000 });
}

/** Crée une famille séparée depuis /onboarding et renvoie son identifiant (lu sur /agenda). */
async function createSeparatedFamily(page: Page, name: string): Promise<string> {
  await page.fill('#name', name);
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });
  await page.goto('/agenda');
  const id = await page.locator('[data-controller="agenda"]').getAttribute('data-agenda-family-id-value');
  expect(id).toBeTruthy();
  return id as string;
}

/** Date `YYYY-MM-DD` à N jours de maintenant + le mois `YYYY-MM` associé. */
function futureDate(days: number): { date: string; month: string } {
  const d = new Date(Date.now() + days * 86_400_000);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return { date: `${y}-${m}-${day}`, month: `${y}-${m}` };
}

function apiHeaders(familyId: string): Record<string, string> {
  return { 'X-Family-Id': familyId, 'Content-Type': 'application/json', Accept: 'application/json' };
}

test('Overlay familles liées : activation → événement lié en lecture seule, désactivation → masqué', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  const emailA = `e2e-overlay-a-${stamp}@example.test`;
  const emailB = `e2e-overlay-b-${stamp}@example.test`;
  const soon = futureDate(3);

  // ---- Famille A ----
  await signUpAndLogin(page, request, emailA, 'Alice Overlay');
  const famA = await createSeparatedFamily(page, 'Foyer A E2E');

  // ---- Famille B + un événement « Judo du second foyer » ----
  await signUpAndLogin(page, request, emailB, 'Bob Overlay');
  const famB = await createSeparatedFamily(page, 'Foyer B E2E');

  await page.goto('/agenda');
  await page.locator('.agenda-create').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await page.check('input[name="wholeFamily"]');
  await page.fill('#a-title', 'Judo du second foyer');
  await page.selectOption('#a-cat', 'sport');
  await page.fill('#a-start', `${soon.date}T18:00`);
  await page.locator('.agenda-form button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  // ---- B propose le lien vers A (initiateur = B, destinataire = A) ----
  const createRes = await page.request.post(`/api/families/${famB}/links`, {
    headers: apiHeaders(famB),
    data: { linkedFamilyId: famA },
  });
  expect(createRes.ok()).toBeTruthy();

  // ---- A se reconnecte et active le lien (seul le destinataire active) ----
  await login(page, emailA);
  const linksRes = await page.request.get(`/api/families/${famA}/links`, { headers: apiHeaders(famA) });
  expect(linksRes.ok()).toBeTruthy();
  const links = (await linksRes.json()) as Array<{ id: string; otherFamilyId: string; status: string }>;
  const pending = links.find((l) => l.otherFamilyId === famB);
  expect(pending, 'lien en attente reçu par A').toBeTruthy();
  const activateRes = await page.request.post(
    `/api/families/${famA}/links/${pending!.id}/activate`,
    { headers: apiHeaders(famA), data: {} },
  );
  expect(activateRes.ok()).toBeTruthy();

  // ---- A : le toggle overlay du lien actif est présent ----
  await page.goto(`/agenda?month=${soon.month}`);
  const toggle = page.locator('.overlay-card input[type="checkbox"]').first();
  await expect(toggle).toHaveCount(1);
  await expect(page.locator('.agenda-overlay-toggles')).toContainText('Événements de la famille liée');

  // ---- Activer l'overlay : l'événement de B apparaît en lecture seule ----
  // On force checked + change : le reload Stimulus détacherait un clic natif (leçon Lot 4).
  await toggle.evaluate((el) => {
    (el as HTMLInputElement).checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.waitForLoadState('networkidle');
  await page.goto(`/agenda?month=${soon.month}`);

  const linked = page.locator('.agenda-event--linked').filter({ hasText: 'Judo du second foyer' });
  await expect(linked).toHaveCount(1);
  await expect(linked.locator('.agenda-linked-badge')).toContainText('Famille liée');
  // Lecture seule stricte : aucune forme d'édition dans la tuile liée.
  await expect(linked.locator('form.agenda-edit')).toHaveCount(0);
  await expect(linked.locator('button')).toHaveCount(0);

  // ---- Désactiver l'overlay : l'événement lié disparaît ----
  const toggleOff = page.locator('.overlay-card input[type="checkbox"]').first();
  await toggleOff.evaluate((el) => {
    (el as HTMLInputElement).checked = false;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await page.waitForLoadState('networkidle');
  await page.goto(`/agenda?month=${soon.month}`);
  await expect(page.locator('.agenda-event--linked')).toHaveCount(0);
});

import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';
import { waitForSaved } from '../helpers/page';

const PASSWORD = 'MotDePasseTresLong123';

/**
 * Le temps réel web (Turbo Streams over Mercure) n'avait jamais été **exercé** de bout en bout : ni
 * la stack de dev ni la suite E2E ne pouvaient pousser quoi que ce soit, si bien qu'un abonnement
 * cassé a survécu à deux lots « terminés » (revue 2026-08, C2/C11 — `<turbo-stream-source>` au lieu
 * de `<turbo-mercure-stream-source>`, donc `private` ignoré et cookie d'abonnement jamais transmis).
 * Le hub tourne désormais dans la stack locale et en CI ; ce fichier est le test qui manquait.
 *
 * La règle qui donne sa valeur au test : **après la mutation, aucune navigation n'est déclenchée
 * ici**. Ni `goto`, ni `reload`, ni clic. Si le contenu apparaît, il ne peut venir que du hub.
 */

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

function apiHeaders(familyId: string): Record<string, string> {
  return { 'X-Family-Id': familyId, 'Content-Type': 'application/json', Accept: 'application/json' };
}

/**
 * Attend que l'abonnement au hub soit **effectivement ouvert**, avant toute mutation.
 *
 * Sans cette étape, un test qui échoue ne dit pas ce qui a manqué — l'abonnement ou la poussée — et
 * un test qui passe pourrait n'avoir mesuré qu'un heureux hasard de reconnexion. `es` est
 * l'`EventSource` que le composant de UX Turbo expose sur son élément ; `1` = `OPEN`.
 */
async function waitForLiveSubscription(page: Page): Promise<void> {
  await expect
    .poll(
      () =>
        page.evaluate(() => {
          const source = document.querySelector('turbo-mercure-stream-source');
          if (!source) return 'absent';
          const es = (source as unknown as { es?: EventSource }).es;
          return es ? es.readyState : 'sans EventSource';
        }),
      { timeout: 15_000, message: "l'abonnement Mercure de l'écran n'est jamais passé à OPEN" },
    )
    .toBe(1);
}

test("Temps réel : un message posté hors de la page arrive sur l'écran ouvert, sans rechargement", async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-rt-${stamp}@example.test`, 'Alice Temps Réel');
  const familyId = await createSeparatedFamily(page, 'Famille Temps Réel E2E');

  const created = await page.request.post(`/api/families/${familyId}/exchanges`, {
    headers: apiHeaders(familyId),
    data: { type: 'discussion', title: 'Sujet poussé en direct' },
  });
  expect(created.ok(), await created.text()).toBeTruthy();
  const exchangeId: string = (await created.json()).id;

  // L'écran de détail s'abonne à DEUX topics : la liste et cet échange précis.
  await page.goto(`/echanges?exchange=${encodeURIComponent(exchangeId)}`);
  await expect(page.locator('.exchange-detail__title')).toContainText('Sujet poussé en direct');
  await waitForLiveSubscription(page);

  // ---- Mutation hors de la page : requête API directe, aucune interaction avec le DOM ----
  const text = `Message arrivé tout seul ${stamp}`;
  const posted = await page.request.post(`/api/exchanges/${encodeURIComponent(exchangeId)}/messages`, {
    headers: apiHeaders(familyId),
    data: { text },
  });
  expect(posted.ok(), await posted.text()).toBeTruthy();

  // À partir d'ici, plus aucune navigation : ce qui apparaît vient du hub.
  await expect(page.locator('.exchange-message__text', { hasText: text })).toBeVisible({
    timeout: 15_000,
  });

  // Et le bandeau « Mises à jour en direct indisponibles » n'a jamais été levé (constat C11) :
  // sans lui, un abonnement en échec passerait pour un écran simplement calme.
  await expect(page.locator('.pwa-banner--warn')).toHaveCount(0);
});

test("Temps réel : une poussée n'efface pas la confirmation de l'action qu'on vient de faire", async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-rt2-${stamp}@example.test`, 'Alice Confirmation');
  const familyId = await createSeparatedFamily(page, 'Famille Confirmation E2E');

  const created = await page.request.post(`/api/families/${familyId}/exchanges`, {
    headers: apiHeaders(familyId),
    data: { type: 'discussion', title: 'Fil de confirmation' },
  });
  expect(created.ok(), await created.text()).toBeTruthy();
  const exchangeId: string = (await created.json()).id;

  await page.goto(`/echanges?exchange=${encodeURIComponent(exchangeId)}`);
  await waitForLiveSubscription(page);

  // ---- Une mutation faite à l'écran : sa confirmation s'affiche ----
  await page.fill('.note-form textarea[name="text"]', `Mon message ${stamp}`);
  await page.locator('.note-form button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.form-success:visible').first()).toContainText('Message envoyé.');

  // ---- Puis une poussée arrive d'ailleurs, et rend l'écran à nouveau ----
  //
  // C'est le scénario qui cassait : le rendu poussé remplaçait le corps de la page, confirmation
  // comprise, et le message avait déjà été retiré du stockage par son premier affichage. Sur un
  // écran temps réel, c'est sa PROPRE mutation qui produit cette poussée — la confirmation
  // disparaissait donc au moment même où elle devait rassurer. Le test est déterministe parce qu'il
  // attend d'abord la preuve que le rendu poussé a bien eu lieu (le message d'à côté), avant de
  // vérifier que la confirmation y a survécu.
  const pushed = `Message d'ailleurs ${stamp}`;
  const posted = await page.request.post(`/api/exchanges/${encodeURIComponent(exchangeId)}/messages`, {
    headers: apiHeaders(familyId),
    data: { text: pushed },
  });
  expect(posted.ok(), await posted.text()).toBeTruthy();

  await expect(page.locator('.exchange-message__text', { hasText: pushed })).toBeVisible({
    timeout: 15_000,
  });
  await expect(page.locator('.form-success:visible').first()).toContainText('Message envoyé.');
});

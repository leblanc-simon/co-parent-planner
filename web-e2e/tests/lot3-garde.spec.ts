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

test('Garde : configurer un rythme → calendrier coloré → échanger un jour', async ({
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
  const stamp = Date.now();
  const adminEmail = `e2e-garde-a-${stamp}@example.test`;
  const parentBEmail = `e2e-garde-b-${stamp}@example.test`;

  // ---- Parent A : compte + famille séparée ----
  await signUpAndLogin(page, request, adminEmail, 'Alice Garde');
  await page.fill('#name', 'Famille Garde E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), {
    timeout: 15_000,
  });

  // ---- Invitation d'un second parent (le rythme exige deux parents distincts) ----
  await page.goto('/settings?tab=famille');
  await page.fill('#i-email', parentBEmail);
  await page.selectOption('#i-role', 'parent');
  await page.locator('[data-settings-target="inviteForm"] button[type="submit"]').click();
  await waitForSaved(page);
  await expect(page.locator('.invitations')).toContainText(parentBEmail);

  // ---- Parent B accepte l'invitation ----
  const inviteToken = await waitForInvitationToken(request, parentBEmail);
  const contextB = await browser.newContext();
  const pageB = await contextB.newPage();
  await signUpAndLogin(pageB, request, parentBEmail, 'Bob Garde');
  await pageB.goto(`/invitations/accept?token=${encodeURIComponent(inviteToken)}`);
  await pageB.click('button[type="submit"]');
  await pageB.waitForURL('**/invitations/accepted', { timeout: 15_000 });
  await contextB.close();

  // ---- Parent A : écran Garde, sans rythme encore configuré ----
  await page.goto('/garde');
  await expect(page.locator('h1')).toContainText('Planning de garde');
  await expect(page.locator('.empty-state')).toContainText('Aucun rythme de garde configuré');

  // ---- Configurer un rythme depuis Paramètres › Rythme de garde (l'édition y a été déplacée) ----
  await page.goto('/settings?tab=garde');
  await page.locator('.rhythm-card:has(input[value="alternating"])').click();
  await page.fill('#g-anchor', '2026-06-21');
  await page.selectOption('#g-weekday', '0'); // dimanche
  await page.selectOption('#g-pa', { label: 'Alice Garde' });
  await page.selectOption('#g-pb', { label: 'Bob Garde' });
  await page.locator('.garde-config button[type="submit"]').click();
  await waitForSaved(page); // succès → rechargement de /settings

  // ---- Retour sur Garde : la génération est synchrone en E2E (Messenger sync://) → calendrier peuplé.
  // On affiche le MOIS SUIVANT, et non le mois courant : le moteur fige le passé, donc le mois
  // courant n'est coloré qu'à partir d'aujourd'hui. En fin de mois les jours restants tombent tous
  // dans la même semaine d'alternance et un seul parent y apparaît — l'assertion A **et** B ne
  // passait alors que selon le jour d'exécution. Le mois suivant est toujours complet.
  const nextMonth = (() => {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  })();
  await page.goto(`/garde?month=${nextMonth}`);
  await expect(page.locator('.garde-cal')).toBeVisible({ timeout: 15_000 });
  await expect(page.locator('.garde-day--a').first()).toBeVisible();
  await expect(page.locator('.garde-day--b').first()).toBeVisible();
  // Les statistiques du mois sont calculées sur les jours effectifs.
  // Tuiles de stats colorées A/B (WG-02) — libellées avec les noms réels des parents + %.
  await expect(page.locator('.garde-stat--a')).toBeVisible();
  await expect(page.locator('.garde-stat--b')).toBeVisible();

  // ---- Marqueur NON CHROMATIQUE de l'attribution (cdc §8, WCAG 1.4.1) ----
  // Les deux fonds A/B ne se distinguent que par la teinte (1,12:1 de luminance) : sans marqueur
  // textuel, le planning est illisible en niveaux de gris, en daltonisme rouge-vert et en contraste
  // forcé. On vérifie ici l'intégration réelle : chaque case attribuée porte un marqueur, et celui
  // de A diffère de celui de B.
  const markerA = page.locator('.garde-day--a .garde-day__parent').first();
  const markerB = page.locator('.garde-day--b .garde-day__parent').first();
  await expect(markerA).not.toBeEmpty();
  await expect(markerB).not.toBeEmpty();
  expect(await markerA.textContent()).not.toBe(await markerB.textContent());
  // Autant de marqueurs que de cases attribuées : aucune case colorée ne reste muette.
  expect(await page.locator('.garde-day__parent').count()).toBe(
    await page.locator('.garde-day--a, .garde-day--b').count(),
  );
  // La légende porte le même marqueur — purement colorée, elle n'expliquerait rien.
  await expect(page.locator('.garde-dot--a').first()).not.toBeEmpty();

  // Sous contraste forcé, le système écrase fonds et bordures : le marqueur, qui est du texte,
  // doit rester visible. C'est le seul canal d'information qui subsiste dans ce mode.
  await page.emulateMedia({ forcedColors: 'active' });
  await expect(markerA).toBeVisible();
  await expect(markerB).toBeVisible();
  await page.emulateMedia({ forcedColors: 'none' });

  // ---- Activités d'agenda en pastilles sur les cases (constat 6.1, maquette `:236-238`) ----
  // Le planning de garde ne montrait aucune activité : le contrôleur ne lisait même pas l'agenda.
  const activityDate = `${nextMonth}-15`;
  await page.goto('/agenda');
  await page.locator('.create-panel').evaluate((d) => {
    (d as HTMLDetailsElement).open = true;
  });
  await page.check('input[name="wholeFamily"]');
  await page.fill('#a-title', 'Cours de guitare');
  await page.selectOption('#a-cat', 'musique');
  await page.fill('#a-start', `${activityDate}T17:30`);
  await page.locator('.agenda-form button[type="submit"]').click();
  await waitForSaved(page);

  await page.goto(`/garde?month=${nextMonth}`);
  const activityCell = page.locator(
    `.garde-day[data-day-url="/garde/jour/${activityDate}"]`,
  );
  await expect(activityCell.locator('.garde-day__event-title')).toContainText(
    'Cours de guitare',
  );
  await expect(activityCell.locator('.garde-day__event-dot--music')).toBeVisible();

  // ---- Fiche de jour (US-CUST-6) : la case ouvre la fiche, l'échange se fait depuis elle ----
  // Le clic sur une case n'échange plus le jour directement : il ouvre la modale, chargée en
  // Turbo Frame. C'est là que vivent « Échanger ce jour » et « Modifier la garde », et c'est la
  // seule interaction dont disposait jusqu'ici un membre en lecture seule : aucune.
  await activityCell.click();
  const daySheet = page.locator('.day-modal .day-sheet');
  await expect(daySheet).toBeVisible({ timeout: 15_000 });
  // La fiche nomme le parent en clair et l'origine de l'attribution — ce que la case ne dit pas.
  await expect(daySheet.locator('.day-sheet__parent-name')).not.toBeEmpty();
  await expect(daySheet.locator('.day-sheet__rule')).not.toBeEmpty();
  // Transmission « Retour chez {Parent} le {date} à {heure} » (US-CUST-6).
  await expect(daySheet.locator('.day-sheet__handover')).toContainText('Retour chez');
  // Activités du jour, avec l'horaire dans le fuseau de la famille.
  await expect(daySheet.locator('.day-sheet__event-title')).toContainText('Cours de guitare');
  await expect(daySheet.locator('.day-sheet__event-time')).toContainText('17:30');
  // Échap referme la modale (comportement natif de <dialog>), sans muter la garde.
  await page.keyboard.press('Escape');
  await expect(daySheet).not.toBeVisible();

  // Réouverture puis échange effectif depuis la fiche → exception d'un jour.
  await activityCell.click();
  await expect(daySheet).toBeVisible();
  await daySheet.getByRole('button', { name: 'Échanger ce jour' }).click();
  await expect(page.locator('.garde-day--exception').first()).toBeVisible({ timeout: 15_000 });
  // L'exception « Échange » figure dans la liste des exceptions, désormais sous Paramètres.
  await page.goto('/settings?tab=garde');
  await expect(page.locator('.garde-list')).toContainText('Échange');

  // ---- Couleurs du planning choisies par le spectateur (cdc §8.2, évolution E5) ----
  // Le planning part sur les deux teintes du §8 : terracotta pour A, teal pour B.
  const slotOf = (name: string) =>
    page
      .locator('.app-shell')
      .evaluate(
        (el, prop) => getComputedStyle(el).getPropertyValue(prop).trim(),
        name,
      );
  expect(await slotOf('--slot-a')).toBe('#C2643D');
  expect(await slotOf('--slot-b')).toBe('#2E7D71');

  // La section est rendue dans l'onglet « Planning de garde » ; chaque pastille porte le NOM de
  // la teinte, seule façon de la choisir quand on ne distingue pas les couleurs (§8.1).
  const colours = page.locator('#sec-colors');
  await expect(colours).toBeVisible();
  await expect(colours.locator('.palette__chip').first()).toContainText('Terracotta');

  // Reprendre la teinte DÉJÀ portée par l'autre parent est refusé : deux slots identiques
  // rendraient le planning illisible, et la sélection revient sur la valeur enregistrée.
  // Le bouton radio est retiré du rendu (il reste focalisable mais invisible) : c'est la
  // pastille, donc le <label>, que l'on clique — le geste réel de l'utilisateur.
  await colours
    .locator('label:has(input[name="custody-color-B"][value="terracotta"])')
    .click();
  await expect(page.locator('[data-custody-colors-target="error"]')).toBeVisible({
    timeout: 15_000,
  });
  await expect(
    colours.locator('input[name="custody-color-B"][value="teal"]'),
  ).toBeChecked();

  // Un choix valide repeint le planning — et seulement celui de ce membre-là.
  await colours
    .locator('label:has(input[name="custody-color-B"][value="purple"])')
    .click();
  await waitForSaved(page);
  expect(await slotOf('--slot-b')).toBe('#8E63A8');
  expect(await slotOf('--slot-a')).toBe('#C2643D');

  await page.goto(`/garde?month=${nextMonth}`);
  expect(await slotOf('--slot-b')).toBe('#8E63A8');
  // §8.1 sans exception : l'initiale reste affichée quelle que soit la palette choisie.
  await expect(page.locator('.garde-day--b .garde-day__parent').first()).not.toBeEmpty();
});

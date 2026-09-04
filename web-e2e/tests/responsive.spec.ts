import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';

/**
 * Smoke responsive (DoD §8.2 : desktop + mobile viewport). Tourne sur les deux projets Playwright
 * (desktop, mobile). Verifie l'absence de debordement horizontal et l'accessibilite de base.
 *
 * Historique : ce garde-fou ne couvrait que `/login` — une page sans barre laterale ni calendrier.
 * Aucun ecran authentifie n'etait teste, si bien que la page debordait horizontalement de 135 px a
 * 1320 px (le gabarit de reference du design system) sur `/children`, et d'environ 480 px sur un
 * viewport de telephone, sans que la DoD ne s'en apercoive. La cause etait double : `1fr` vaut
 * `minmax(auto, 1fr)` (une colonne de grille ne descend donc pas sous son min-content), et le bloc
 * `@media (max-width: 900px)` etait declare AVANT les composants qu'il replie — a specificite egale
 * la derniere regle gagne, le repli de `/garde`, `/settings` et `/echanges` etait donc mort.
 */

const PASSWORD = 'MotDePasseTresLong123';

/** Ecrans applicatifs a verifier : tous portent la barre laterale et au moins une grille. */
const APP_SCREENS = ['/', '/garde', '/agenda', '/children', '/echanges', '/settings'] as const;

async function hasHorizontalScroll(page: Page): Promise<boolean> {
  return page.evaluate(
    () =>
      document.documentElement.scrollWidth >
      document.documentElement.clientWidth + 1,
  );
}

test('la page de connexion s\'affiche sans debordement horizontal', async ({
  page,
}) => {
  await page.goto('/login');
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();

  expect(await hasHorizontalScroll(page), 'pas de scroll horizontal').toBeFalsy();
});

test('les ecrans authentifies ne debordent pas horizontalement', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const email = `e2e-responsive-${Date.now()}@example.test`;

  await page.goto('/register');
  await page.fill('#fullName', 'Rita Responsive');
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

  await page.fill('#name', 'Famille Responsive E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), {
    timeout: 15_000,
  });

  // Un enfant : la fiche enfant est l'ecran le plus dense (master-detail a trois colonnes),
  // c'est celui qui debordait a la largeur de reference.
  await page.goto('/children');
  await page.locator('.create-panel > summary').click();
  await page.fill('#nc-name', 'Sacha');
  await page.locator('.create-panel__form button[type="submit"]').click();
  await expect(page.locator('.child-card__name')).toContainText('Sacha');

  for (const screen of APP_SCREENS) {
    await page.goto(screen);
    await expect(page.locator('.app-shell')).toBeVisible();
    expect(
      await hasHorizontalScroll(page),
      `débordement horizontal sur ${screen} (viewport ${page.viewportSize()?.width}px)`,
    ).toBeFalsy();
  }
});

import { expect, test } from '@playwright/test';

/**
 * Smoke responsive (DoD §8.2 : desktop + mobile viewport). Tourne sur les deux
 * projets Playwright (desktop, mobile). Verifie l'absence de debordement
 * horizontal et l'accessibilite de base des champs.
 */
test('la page de connexion s\'affiche sans debordement horizontal', async ({
  page,
}) => {
  await page.goto('/login');
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();

  const hasHorizontalScroll = await page.evaluate(
    () =>
      document.documentElement.scrollWidth >
      document.documentElement.clientWidth + 1,
  );
  expect(hasHorizontalScroll, 'pas de scroll horizontal').toBeFalsy();
});

import { Page, expect, test } from '@playwright/test';

import { waitForVerificationToken } from '../helpers/mailpit';
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

test('Notifications : désactiver une catégorie et régler les quiet-hours (persistance)', async ({
  page,
  request,
}: {
  page: Page;
  request: import('@playwright/test').APIRequestContext;
}) => {
  const stamp = Date.now();
  await signUpAndLogin(page, request, `e2e-notif-${stamp}@example.test`, 'Alice Notif');

  await page.fill('#name', 'Famille Notif E2E');
  await page.selectOption('#type', 'separated');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/onboarding'), { timeout: 15_000 });

  await page.goto('/settings');
  const section = page.locator('#sec-notifications');
  await expect(section).toContainText('Notifications');

  // Par défaut (opt-out) : Agenda est activé.
  const agenda = section.locator('input[name="notif[agenda]"]');
  await expect(agenda).toBeChecked();

  // Désactiver Agenda (la case native est recouverte par le slider iOS → on force `checked`).
  await agenda.evaluate((el) => {
    (el as HTMLInputElement).checked = false;
  });
  await section.locator('#qh-start').fill('22:00');
  await section.locator('#qh-end').fill('07:00');
  await section.locator('button[type="submit"]').click();
  await waitForSaved(page);

  // Après rechargement : la préférence et les quiet-hours sont persistées.
  await page.goto('/settings');
  const reloaded = page.locator('#sec-notifications');
  await expect(reloaded.locator('input[name="notif[agenda]"]')).not.toBeChecked();
  await expect(reloaded.locator('input[name="notif[custody]"]')).toBeChecked();
  await expect(reloaded.locator('#qh-start')).toHaveValue('22:00');
  await expect(reloaded.locator('#qh-end')).toHaveValue('07:00');
});

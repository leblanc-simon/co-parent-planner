import { Page, expect } from '@playwright/test';

/**
 * Attend qu'un écran ait fini de charger.
 *
 * À utiliser **à la place de `waitForLoadState('networkidle')`** : les écrans temps réel (Garde,
 * Agenda, Enfants, Échanges) tiennent un flux SSE ouvert en permanence vers le hub Mercure, si bien
 * que le réseau n'est jamais « inactif » et que l'attente expire systématiquement. Le défaut est
 * resté invisible tant que le temps réel ne fonctionnait pas (revue 2026-08 C2/C11 : hub par défaut
 * sur `example.com`, abonnement anonyme) — il est apparu dès la première suite lancée avec un hub
 * joignable.
 *
 * `load` suffit ici : les assertions Playwright qui suivent réessaient jusqu'à leur timeout, ce qui
 * couvre le rendu asynchrone (Turbo, Stimulus) sans dépendre de l'inactivité du réseau.
 */
export async function waitForScreen(page: Page): Promise<void> {
  await page.waitForLoadState('load');
}

/**
 * Attend qu'une **mutation** lancée par un contrôleur Stimulus ait abouti.
 *
 * Les écrans applicatifs mutent via l'API puis rechargent la page ; la confirmation de succès du
 * socle `ApiController` (transportée par `sessionStorage` pour survivre au rechargement) est donc le
 * seul signal fiable que la requête est partie, a réussi, ET que la page rechargée est à l'écran.
 * Sans elle, un `goto()` enchaîné trop tôt annulait la requête en vol et le test constatait une
 * absence de persistance qui n'existait pas.
 */
export async function waitForSaved(page: Page): Promise<void> {
  await expect(page.locator('.form-success:visible').first()).toBeVisible({ timeout: 15_000 });
}

/**
 * Attend le **rechargement** que `ApiController._onSuccess` déclenche après une mutation.
 *
 * À armer AVANT de déclencher la mutation, et à réserver aux mutations qui n'affichent aucune
 * confirmation (bascule d'overlay) — les autres se suivent par {@link waitForSaved}. Attendre la
 * seule réponse de l'API ne suffit pas : le rechargement part juste après, et un `goto()` enchaîné
 * entre en collision avec lui (`net::ERR_ABORTED`).
 */
export function waitForReload(page: Page): Promise<unknown> {
  return page.waitForEvent('load', { timeout: 15_000 });
}

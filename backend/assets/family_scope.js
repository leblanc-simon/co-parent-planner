/*
 * Famille active du front web (US-SCOPE-1).
 *
 * Le cookie `cpp_family` est lu côté serveur par `ActiveFamilyResolver` (rendu des
 * pages) ET côté JS (header `X-Family-Id` des appels API) : les deux endroits qui
 * l'écrivaient — le sélecteur de famille et, désormais, la création d'une famille —
 * partagent cette seule définition plutôt que d'en recopier la forme.
 */
const ONE_YEAR = 31536000;

export function setActiveFamily(familyId) {
    if (!familyId) return false;
    document.cookie = `cpp_family=${familyId}; path=/; max-age=${ONE_YEAR}; samesite=lax`;
    return true;
}

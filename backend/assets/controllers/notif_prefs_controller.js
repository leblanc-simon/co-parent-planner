import { ApiController } from '../api_base.js';

/*
 * Préférences de notification (Lot 7, US-SET-1) : PUT /api/me/notification-prefs (scope utilisateur,
 * pas de X-Family-Id — `familyIdValue` reste vide, le socle n'envoie alors pas l'en-tête).
 * Succès → rechargement (état canonique) avec confirmation explicite.
 */
export default class extends ApiController {
    save(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const email = {};
        f.querySelectorAll('[name^="notif["]').forEach((cb) => {
            email[cb.name.replace(/^notif\[(.+)\]$/, '$1')] = cb.checked;
        });
        const body = {
            email,
            quietHoursStart: f.querySelector('[name="quietHoursStart"]').value || null,
            quietHoursEnd: f.querySelector('[name="quietHoursEnd"]').value || null,
        };
        this._send('PUT', '/api/me/notification-prefs', body, {
            event,
            notice: 'Préférences de notification enregistrées.',
        });
    }
}

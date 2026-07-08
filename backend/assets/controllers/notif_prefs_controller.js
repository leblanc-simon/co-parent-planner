import { Controller } from '@hotwired/stimulus';

/*
 * Préférences de notification (Lot 7, US-SET-1) : PUT /api/me/notification-prefs (scope utilisateur,
 * pas de X-Family-Id). Succès → rechargement (état canonique).
 */
export default class extends Controller {
    static targets = ['error'];

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
        this._send('PUT', '/api/me/notification-prefs', body);
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(body),
                credentials: 'same-origin',
            });
            if (response.ok) {
                window.location.reload();
                return;
            }
            const data = await response.json().catch(() => ({}));
            this._showError(data.detail || data['hydra:description'] || 'Une erreur est survenue.');
        } catch (e) {
            this._showError('Connexion impossible. Réessayez.');
        }
    }

    _showError(message) {
        if (!this.hasErrorTarget) return;
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

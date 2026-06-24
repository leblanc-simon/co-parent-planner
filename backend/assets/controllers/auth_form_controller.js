import { Controller } from '@hotwired/stimulus';

/*
 * Soumet un formulaire d'auth en JSON à l'API (décision D2 : le web consomme l'API ; les cookies
 * HttpOnly sont posés par le serveur). En cas de succès : redirige (data-...-redirect-value) ou
 * affiche le message de la réponse. En cas d'erreur : affiche le `detail` RFC 7807.
 */
export default class extends Controller {
    static targets = ['error', 'notice', 'form', 'submit'];
    static values = { redirect: String, auto: Boolean };

    connect() {
        if (this.autoValue) {
            this.submit();
        }
    }

    async submit(event) {
        if (event) event.preventDefault();
        const form = this.hasFormTarget ? this.formTarget : this.element;
        const payload = Object.fromEntries(new FormData(form).entries());

        if (this.hasErrorTarget) this.errorTarget.textContent = '';
        if (this.hasSubmitTarget) this.submitTarget.disabled = true;

        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (response.ok) {
                if (this.redirectValue) {
                    window.location.assign(this.redirectValue);
                    return;
                }
                const body = await response.json().catch(() => ({}));
                if (this.hasNoticeTarget) {
                    this.noticeTarget.textContent = body.message || 'Opération réussie.';
                    this.noticeTarget.hidden = false;
                }
                form.hidden = true;
                return;
            }

            const body = await response.json().catch(() => ({}));
            const message = body.detail || body['hydra:description'] || 'Une erreur est survenue.';
            if (this.hasErrorTarget) this.errorTarget.textContent = message;
        } catch (e) {
            if (this.hasErrorTarget) this.errorTarget.textContent = 'Connexion impossible. Réessayez.';
        } finally {
            if (this.hasSubmitTarget) this.submitTarget.disabled = false;
        }
    }
}

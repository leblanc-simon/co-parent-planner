import { Controller } from '@hotwired/stimulus';

/*
 * Soumet un formulaire d'auth en JSON à l'API (décision D2 : le web consomme l'API ; les cookies
 * HttpOnly sont posés par le serveur). En cas de succès : redirige (data-...-redirect-value) ou
 * affiche le message de la réponse.
 *
 * En cas d'erreur, on affiche les **violations** de validation plutôt que le `detail` RFC 7807 :
 * celui-ci préfixe chaque violation du nom technique du champ, si bien que l'écran d'inscription
 * annonçait « password: Cette chaîne est trop courte… » (revue 2026-08 C12). Même règle que le socle
 * `ApiController` des écrans applicatifs — ces écrans-ci ne l'utilisent pas (ils ne sont pas scopés
 * famille), la logique est donc dupliquée ici, en une seule méthode.
 */
export default class extends Controller {
    static targets = ['error', 'notice', 'form', 'submit', 'pending'];
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
            this._showError(this._errorMessage(body));
        } catch (e) {
            this._showError('Connexion impossible. Réessayez.');
        } finally {
            if (this.hasSubmitTarget) this.submitTarget.disabled = false;
        }
    }

    /** Violations localisées d'abord (sans le nom technique du champ), `detail` en dernier recours. */
    _errorMessage(data) {
        if (Array.isArray(data.violations) && data.violations.length > 0) {
            // Dédoublonnage : deux contraintes d'un même champ pouvaient renvoyer le même libellé.
            const messages = [...new Set(data.violations.map((v) => v.message).filter(Boolean))];
            if (messages.length > 0) return messages.join(' ');
        }

        return data.detail || data['hydra:description'] || 'Une erreur est survenue.';
    }

    /**
     * Affiche l'erreur et masque le texte d'attente : sur `/verify-email`, l'erreur s'ajoutait sous
     * « Validation de votre adresse en cours… », qui restait affiché (revue C12).
     */
    _showError(message) {
        if (this.hasPendingTarget) this.pendingTarget.hidden = true;
        if (this.hasErrorTarget) this.errorTarget.textContent = message;
    }
}

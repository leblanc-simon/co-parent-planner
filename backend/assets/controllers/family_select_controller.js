import { Controller } from '@hotwired/stimulus';

/* Sélecteur de famille (cdc §2.1, US-SCOPE-1) : ouvre/ferme le menu, ferme au clic extérieur,
 * et persiste la famille active dans un cookie (`cpp_family`) avant de recharger la page. */
export default class extends Controller {
    static targets = ['menu'];

    select(event) {
        const familyId = event.currentTarget.dataset.familyId;
        if (!familyId) return;
        // Cookie lisible serveur (ActiveFamilyResolver) ET JS (header X-Family-Id des appels API).
        document.cookie = `cpp_family=${familyId}; path=/; max-age=31536000; samesite=lax`;
        window.location.reload();
    }

    connect() {
        this._onOutside = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this._onOutside);
    }

    disconnect() {
        document.removeEventListener('click', this._onOutside);
    }

    toggle(event) {
        event.stopPropagation();
        this.menuTarget.classList.toggle('is-open');
    }

    close() {
        if (this.hasMenuTarget) {
            this.menuTarget.classList.remove('is-open');
        }
    }
}

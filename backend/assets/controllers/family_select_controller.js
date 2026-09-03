import { Controller } from '@hotwired/stimulus';
import { setActiveFamily } from '../family_scope.js';

/* Sélecteur de famille (cdc §2.1, US-SCOPE-1) : ouvre/ferme le menu, ferme au clic extérieur,
 * et persiste la famille active dans un cookie (`cpp_family`) avant de recharger la page. */
export default class extends Controller {
    static targets = ['menu'];

    select(event) {
        if (!setActiveFamily(event.currentTarget.dataset.familyId)) return;
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

import { Controller } from '@hotwired/stimulus';

/* Sélecteur de famille (cdc §2.1) : ouvre/ferme le menu, ferme au clic extérieur.
 * La bascule effective de famille active (X-Family-Id) est câblée aux lots suivants. */
export default class extends Controller {
    static targets = ['menu'];

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

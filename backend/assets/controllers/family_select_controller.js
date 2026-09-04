import { Controller } from '@hotwired/stimulus';
import { setActiveFamily } from '../family_scope.js';

/* Sélecteur de famille (cdc §2.1, US-SCOPE-1) : ouvre/ferme le menu, ferme au clic extérieur,
 * et persiste la famille active dans un cookie (`cpp_family`) avant de recharger la page. */
export default class extends Controller {
    static targets = ['menu', 'button'];

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
        // Échap ferme le menu et rend le focus au déclencheur : sans cela, la seule sortie était un
        // clic ailleurs — un geste qu'un utilisateur au clavier n'a pas.
        this._onKeydown = (event) => {
            if ('Escape' === event.key && this._isOpen()) {
                this.close();
                if (this.hasButtonTarget) this.buttonTarget.focus();
            }
        };
        document.addEventListener('click', this._onOutside);
        document.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this._onOutside);
        document.removeEventListener('keydown', this._onKeydown);
    }

    toggle(event) {
        event.stopPropagation();
        this._setOpen(!this._isOpen());
    }

    close() {
        this._setOpen(false);
    }

    _isOpen() {
        return this.hasMenuTarget && !this.menuTarget.hidden;
    }

    /** `hidden` plutôt qu'une classe : l'état est alors le même pour l'œil et pour l'assistance. */
    _setOpen(open) {
        if (!this.hasMenuTarget) return;
        this.menuTarget.hidden = !open;
        this.menuTarget.classList.toggle('is-open', open);
        if (this.hasButtonTarget) this.buttonTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

import { Controller } from '@hotwired/stimulus';

/*
 * Mutations de l'écran Échanges (Lot 6) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 */
export default class extends Controller {
    static targets = ['error', 'type', 'options', 'optionsRows'];
    static values = { familyId: String };

    connect() {
        this.toggleType();
    }

    toggleType() {
        if (!this.hasTypeTarget || !this.hasOptionsTarget) return;
        this.optionsTarget.hidden = this.typeTarget.value !== 'decision';
    }

    addOption(event) {
        event.preventDefault();
        if (!this.hasOptionsRowsTarget) return;
        const row = document.createElement('div');
        row.className = 'exchange-option-row';
        row.innerHTML =
            '<input name="optionLabel[]" placeholder="Libellé de l\'option" maxlength="200">' +
            '<input name="optionSub[]" placeholder="Précision (optionnel)" maxlength="255">';
        this.optionsRowsTarget.appendChild(row);
    }

    createExchange(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const type = this._val(f, 'type');
        const body = {
            type,
            title: this._val(f, 'title'),
            body: this._val(f, 'body') || null,
            childId: this._val(f, 'childId') || null,
            deadline: this._val(f, 'deadline') || null,
        };
        if (type === 'decision') {
            const labels = [...f.querySelectorAll('[name="optionLabel[]"]')];
            const subs = [...f.querySelectorAll('[name="optionSub[]"]')];
            body.options = labels
                .map((el, i) => ({ label: el.value.trim(), sub: subs[i] ? subs[i].value.trim() || null : null }))
                .filter((o) => o.label !== '');
        }
        this._send('POST', `/api/families/${this.familyIdValue}/exchanges`, body);
    }

    postMessage(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('POST', `/api/exchanges/${f.dataset.exchangeId}/messages`, { text: this._val(f, 'text') });
    }

    decide(event) {
        const el = event.currentTarget;
        this._send('POST', `/api/exchanges/${el.dataset.exchangeId}/decide`, { optionId: el.dataset.optionId });
    }

    closeExchange(event) {
        if (!window.confirm('Clore cet échange ?')) return;
        this._send('PATCH', `/api/exchanges/${event.currentTarget.dataset.exchangeId}`, { status: 'decided' });
    }

    _val(form, name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el ? el.value : '';
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'PATCH' === method ? 'application/merge-patch+json' : 'application/json',
                    Accept: 'application/json',
                    'X-Family-Id': this.familyIdValue,
                },
                body: null === body ? undefined : JSON.stringify(body),
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

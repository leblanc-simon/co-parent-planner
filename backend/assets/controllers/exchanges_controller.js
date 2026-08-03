import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Échanges (Lot 6) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 */
export default class extends ApiController {
    static targets = ['type', 'options', 'optionsRows'];

    connect() {
        super.connect();
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
            '<input name="optionLabel[]" placeholder="Libellé de l\'option" maxlength="200" aria-label="Libellé de l\'option">' +
            '<input name="optionSub[]" placeholder="Précision (optionnel)" maxlength="255" aria-label="Précision de l\'option">';
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
        this._send('POST', `/api/families/${this.familyIdValue}/exchanges`, body, {
            event,
            notice: 'decision' === type ? 'Décision ouverte.' : 'Discussion ouverte.',
            idempotent: true,
        });
    }

    postMessage(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('POST', `/api/exchanges/${f.dataset.exchangeId}/messages`, { text: this._val(f, 'text') }, {
            event,
            notice: 'Message envoyé.',
            idempotent: true,
        });
    }

    // Voter pour une option : action principale de l'écran, donc sans confirmation (le consensus,
    // lui, est arbitré côté serveur). L'ambiguïté du libellé est traitée dans le gabarit.
    decide(event) {
        const el = event.currentTarget;
        this._send('POST', `/api/exchanges/${el.dataset.exchangeId}/decide`, { optionId: el.dataset.optionId }, {
            event,
            notice: 'Votre choix est enregistré.',
        });
    }

    closeExchange(event) {
        if (!window.confirm('Clore cet échange ?')) return;
        this._send('PATCH', `/api/exchanges/${event.currentTarget.dataset.exchangeId}`, { status: 'decided' }, {
            event,
            notice: 'Échange clos.',
        });
    }

    _val(form, name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el ? el.value : '';
    }
}

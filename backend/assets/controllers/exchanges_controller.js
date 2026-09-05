import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Échanges (Lot 6) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 */
export default class extends ApiController {
    static targets = ['type', 'options', 'optionsRows', 'option', 'submitVote', 'more'];
    static values = { unreadId: String };

    connect() {
        super.connect();
        this.toggleType();
        this.markSelectedRead();
    }

    /**
     * Marque lu l'échange affiché (X5).
     *
     * Volontairement **silencieux** : ni bandeau de confirmation, ni bandeau d'erreur, ni
     * rechargement — `_send()` fait les trois. Marquer lu n'est pas un geste de l'utilisateur, c'est
     * la conséquence de son regard ; l'annoncer serait du bruit, et un échec ne coûte qu'une
     * pastille qui reste jusqu'à la prochaine ouverture.
     */
    async markSelectedRead() {
        const id = this.unreadIdValue;
        if (!id) return;
        try {
            const url = `/api/exchanges/${id}/read`;
            let response = await this._fetch('POST', url, {}, null);
            if (401 === response.status && (await this._refreshSession())) {
                response = await this._fetch('POST', url, {}, null);
            }
            if (!response.ok) return;
        } catch (e) {
            return;
        }
        // La pastille tombe sans rechargement : la page affichée est déjà la bonne, seul ce
        // marqueur-là est devenu faux.
        const card = document.getElementById(`exchange-card-${id}`);
        if (!card) return;
        card.classList.remove('exchange-card--unread');
        card.querySelector('.exchange-card__unread')?.remove();
    }

    toggleType() {
        if (!this.hasTypeTarget || !this.hasOptionsTarget) return;
        const decision = this.typeTarget.value === 'decision';
        this.optionsTarget.hidden = !decision;
        // Une décision a presque toujours une échéance : choisir « Décision » déplie les champs
        // secondaires (point 8). On ne les replie pas ensuite — refermer sous les doigts de qui
        // vient d'y saisir quelque chose serait pire que de laisser ouvert.
        if (decision && this.hasMoreTarget) this.moreTarget.open = true;
    }

    /**
     * Point 7 — fixer ou corriger l'échéance depuis l'écran.
     *
     * `UpdateExchangeRequest` l'accepte depuis toujours (`''` efface) ; aucune UI ne l'offrait, si
     * bien qu'une échéance mal saisie ne se corrigeait qu'en rouvrant un autre échange.
     */
    updateDeadline(event) {
        event.preventDefault();
        const f = event.currentTarget;
        this._send('PATCH', `/api/exchanges/${f.dataset.exchangeId}`, { deadline: this._val(f, 'deadline') }, {
            event,
            notice: this._val(f, 'deadline') ? 'Échéance enregistrée.' : 'Échéance retirée.',
        });
    }

    /** Retirer l'échéance : `''` l'efface côté serveur (`null` signifierait « inchangée »). */
    clearDeadline(event) {
        this._send('PATCH', `/api/exchanges/${event.currentTarget.dataset.exchangeId}`, { deadline: '' }, {
            event,
            notice: 'Échéance retirée.',
        });
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
    /**
     * Sélection d'une option — **locale**, rien ne part au serveur (X1).
     *
     * Chaque option portait auparavant son propre bouton « Valider » : un clic envoyait le vote, et
     * comme `DecisionEngine` ne tranche qu'une fois TOUS les électeurs ayant voté, le même geste
     * était tantôt un brouillon modifiable, tantôt une signature définitive — sans que rien ne les
     * distingue. On sépare donc le choix de l'engagement.
     */
    chooseOption(event) {
        const option = event.currentTarget;
        this.optionTargets.forEach((el) => {
            const chosen = el === option;
            el.classList.toggle('is-chosen', chosen);
            el.setAttribute('aria-checked', chosen ? 'true' : 'false');
        });
        if (this.hasSubmitVoteTarget) {
            this.submitVoteTarget.disabled = false;
            this.submitVoteTarget.dataset.optionId = option.dataset.optionId;
        }
    }

    /** Engagement du vote : c'est ce bouton-ci qui écrit, et lui seul. */
    submitVote(event) {
        const el = event.currentTarget;
        const optionId = el.dataset.optionId;
        if (!optionId) return;
        // Confirmation demandée UNIQUEMENT quand le vote peut trancher : la réclamer à chaque fois
        // apprendrait à passer outre, et la plupart des votes sont modifiables.
        if ('true' === el.dataset.maySettle
            && !window.confirm('Tous les autres membres ont voté : votre choix peut trancher définitivement cette décision. Continuer ?')) {
            return;
        }
        this._send('POST', `/api/exchanges/${el.dataset.exchangeId}/decide`, { optionId }, {
            event,
            notice: 'Votre choix est enregistré.',
        });
    }

    /**
     * Trancher une décision que personne ne vote (X2).
     *
     * Le serveur l'accepte depuis toujours — `ExchangeWriter` clôt une décision en forçant l'option
     * retenue — mais aucun écran ne l'offrait : une décision non votée restait ouverte, et « en
     * retard », indéfiniment.
     */
    settleDecision(event) {
        const el = event.currentTarget;
        const optionId = this.hasSubmitVoteTarget ? this.submitVoteTarget.dataset.optionId : null;
        if (!optionId) {
            this._showError('Choisissez d\'abord l\'option à retenir.');
            return;
        }
        if (!window.confirm('Retenir cette option et clore la décision ? Cette action est irréversible.')) return;
        this._send('PATCH', `/api/exchanges/${el.dataset.exchangeId}`, { decidedOptionId: optionId }, {
            event,
            notice: 'Décision tranchée.',
        });
    }

    closeExchange(event) {
        if (!window.confirm('Clore cet échange ? Il ne pourra plus être rouvert.')) return;
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

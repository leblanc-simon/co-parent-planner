import { ApiController } from '../api_base.js';

/*
 * Mutations de l'écran Dépenses (Lot 10) via l'API REST, scopées X-Family-Id. Succès → rechargement
 * (état canonique ; le temps réel Mercure rafraîchit aussi chez les autres membres).
 *
 * Le montant se saisit en euros et voyage en **centimes entiers** : la conversion a lieu ici, une
 * seule fois, via un arrondi explicite. `Math.round(42.35 * 100)` vaut 4235, là où `42.35 * 100`
 * seul donne 4234.999999999999 — un centime perdu à chaque saisie, et un solde qui ne tombe jamais
 * juste.
 */
export default class extends ApiController {
    createExpense(event) {
        event.preventDefault();
        const f = event.currentTarget;
        const body = {
            title: this._val(f, 'title'),
            category: this._val(f, 'category'),
            payerMembershipId: this._val(f, 'payerMembershipId'),
            amountCents: this._cents(this._val(f, 'amount')),
            expenseDate: this._val(f, 'expenseDate'),
            childIds: [...f.querySelectorAll('[name="childIds[]"]:checked')].map((c) => c.value),
        };

        const shares = this._shares(f);
        if (shares.length > 0) body.shares = shares;

        this._send('POST', `/api/families/${this.familyIdValue}/expenses`, body, {
            event,
            notice: 'Dépense enregistrée.',
            idempotent: true,
        });
    }

    settle(event) {
        const id = event.currentTarget.dataset.expenseId;
        this._send('PATCH', `/api/expenses/${id}`, { settled: true }, {
            event,
            notice: 'Dépense marquée réglée.',
        });
    }

    remind(event) {
        const id = event.currentTarget.dataset.expenseId;
        this._send('POST', `/api/expenses/${id}/reminder`, null, {
            event,
            notice: 'Rappel envoyé.',
        });
    }

    deleteExpense(event) {
        const title = event.currentTarget.dataset.title || 'cette dépense';
        // Suppression irréversible : elle se confirme, comme toute action destructive de l'app.
        if (!window.confirm(`Supprimer « ${title} » ? Cette action est définitive.`)) return;

        const id = event.currentTarget.dataset.expenseId;
        this._send('DELETE', `/api/expenses/${id}`, null, {
            event,
            notice: 'Dépense supprimée.',
        });
    }

    /**
     * Partage personnalisé : seules les lignes renseignées comptent. Tout laisser vide garde le
     * 50/50 par défaut du serveur — on n'envoie alors pas de bloc `shares` du tout, plutôt qu'un
     * partage à zéro que l'API refuserait.
     */
    _shares(form) {
        const rows = [...form.querySelectorAll('[name="sharePercent[]"]')];
        if (rows.every((r) => '' === r.value.trim())) return [];

        return rows.map((r) => ({
            membershipId: r.dataset.membershipId,
            shareType: 'percent',
            value: parseInt(r.value || '0', 10),
        }));
    }

    _cents(raw) {
        const amount = parseFloat(String(raw).replace(',', '.'));

        return Number.isFinite(amount) ? Math.round(amount * 100) : 0;
    }

    _val(form, name) {
        return form.querySelector(`[name="${name}"]`)?.value ?? '';
    }
}

import { ApiController } from '../api_base.js';

/*
 * Choix des couleurs du planning de garde (cdc §8.2, évolution E5), dans Paramètres › Planning de
 * garde. La préférence est LOCALE AU SPECTATEUR : elle n'écrit rien de la famille et ne modifie
 * l'écran de personne d'autre — d'où le droit requis, `custody ≥ view` et non `custody:edit`.
 *
 * Le rendu vient du serveur (variables CSS posées sur `.app-shell`) : après un enregistrement, le
 * rechargement de `_send()` suffit à repeindre le planning, et rien n'est peint côté client avant
 * que le serveur l'ait accepté.
 */
export default class extends ApiController {
    static targets = ['choice'];

    connect() {
        super.connect();
        // Sélection enregistrée, par groupe de boutons : elle sert à revenir en arrière quand le
        // serveur refuse (les deux parents ne peuvent pas partager une teinte).
        this._chosen = new Map();
        this.choiceTargets.forEach((input) => {
            if (input.checked) this._chosen.set(input.name, input);
        });
    }

    choose(event) {
        const input = event.currentTarget;
        this._refused = this._chosen.get(input.name) ?? null;
        this._chosen.set(input.name, input);
        this._send('PUT', `/api/families/${this.familyIdValue}/custody-colors`, {
            targetMembershipId: input.dataset.targetId,
            palette: input.value,
        }, { event, notice: 'Couleurs du planning enregistrées.' });
    }

    /**
     * Un choix refusé (même teinte pour les deux parents) est REMIS sur la valeur enregistrée :
     * laisser le bouton coché à côté du message d'erreur ferait croire au choix appliqué, alors que
     * le planning, lui, n'a pas bougé.
     */
    _showError(message) {
        if (this._refused) {
            this._refused.checked = true;
            this._chosen.set(this._refused.name, this._refused);
            this._refused = null;
        }
        super._showError(message);
    }
}

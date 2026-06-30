import { Controller } from '@hotwired/stimulus';

/*
 * Pilote les mutations de l'écran Paramètres › Compte & Famille (Lot 1) en appelant l'API REST,
 * scopées par le header X-Family-Id (famille active). Le serveur reste l'autorité (Voters) ; en cas
 * de succès la page est rechargée pour refléter l'état canonique, sinon le `detail` RFC 7807 s'affiche.
 */
export default class extends Controller {
    static targets = ['error', 'summary', 'inviteRole', 'inviteRights'];
    static values = { familyId: String };

    // ---- Réglages famille ----
    saveFamily(event) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const body = {};
        // Champs requis/non effaçables : envoyés seulement si non vides.
        ['name', 'type', 'timezone'].forEach((f) => {
            const v = data.get(f);
            if (v !== null && v !== '') body[f] = v;
        });
        // Champs optionnels EFFACABLES : un champ vidé est envoyé à null pour effacer la valeur serveur.
        const academy = data.get('academy');
        body.academy = academy === '' || academy === null ? null : academy;
        const zone = data.get('schoolZone');
        body.schoolZone = zone === '' || zone === null ? null : zone;
        const hw = data.get('handoverWeekday');
        body.handoverWeekday = hw === '' || hw === null ? null : parseInt(hw, 10);
        const ht = data.get('handoverTime');
        body.handoverTime = ht === '' || ht === null ? null : ht;
        this._send('PATCH', `/api/families/${this.familyIdValue}`, body);
    }

    // ---- Membres ----
    saveMember(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.dataset.membershipId;
        // N'envoyer QUE les champs dont le contrôle est activé : un non-admin n'édite que sa couleur,
        // donc role/rights/isAdmin (désactivés) ne doivent pas figurer dans la requête (sinon 403).
        const body = {};
        const role = form.querySelector('[name="role"]');
        if (role && !role.disabled) body.role = role.value;
        const admin = form.querySelector('[name="isAdmin"]');
        if (admin && !admin.disabled) body.isAdmin = admin.checked;
        const color = form.querySelector('[name="displayColor"]');
        if (color && !color.disabled) body.displayColor = color.value;
        const rights = this._rights(form);
        if (Object.keys(rights).length) body.rights = rights;
        this._send('PATCH', `/api/memberships/${id}`, body);
    }

    removeMember(event) {
        if (!window.confirm('Retirer ce membre de la famille ?')) return;
        this._send('DELETE', `/api/memberships/${event.currentTarget.dataset.membershipId}`);
    }

    // ---- Invitations ----
    invite(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const body = {
            email: form.querySelector('[name="email"]').value,
            role: form.querySelector('[name="role"]').value,
            rights: this._rights(form),
        };
        this._send('POST', `/api/families/${this.familyIdValue}/invitations`, body);
    }

    revokeInvitation(event) {
        this._send('DELETE', `/api/invitations/${event.currentTarget.dataset.invitationId}`);
    }

    // ---- Liens entre familles ----
    createLink(event) {
        event.preventDefault();
        const linkedFamilyId = event.currentTarget.querySelector('[name="linkedFamilyId"]').value;
        this._send('POST', `/api/families/${this.familyIdValue}/links`, { linkedFamilyId });
    }

    activateLink(event) {
        this._send('POST', `/api/families/${this.familyIdValue}/links/${event.currentTarget.dataset.linkId}/activate`);
    }

    removeLink(event) {
        if (!window.confirm('Supprimer ce lien entre familles ?')) return;
        this._send('DELETE', `/api/families/${this.familyIdValue}/links/${event.currentTarget.dataset.linkId}`);
    }

    // ---- Récapitulatif des droits avant envoi (CA §3.8) ----
    refreshSummary() {
        if (!this.hasSummaryTarget) return;
        const role = this.hasInviteRoleTarget ? this.inviteRoleTarget.value : 'step_parent';
        if (role === 'parent') {
            this.summaryTarget.textContent = 'Un parent dispose de tous les droits (édition partout).';
            return;
        }
        const parts = [];
        this.inviteRightsTarget.querySelectorAll('select').forEach((sel) => {
            const domain = sel.name.replace(/^rights\[(.+)\]$/, '$1');
            if (sel.value !== 'none') parts.push(`${domain}: ${sel.value}`);
        });
        this.summaryTarget.textContent = parts.length ? `Droits proposés — ${parts.join(', ')}.` : 'Aucun droit accordé (membre en lecture seule de rien).';
    }

    // ---- Helpers ----
    _rights(form) {
        const rights = {};
        form.querySelectorAll('select[name^="rights["]').forEach((sel) => {
            if (sel.disabled) return;
            const domain = sel.name.replace(/^rights\[(.+)\]$/, '$1');
            rights[domain] = sel.value;
        });
        return rights;
    }

    async _send(method, url, body) {
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': method === 'PATCH' ? 'application/merge-patch+json' : 'application/json',
                    Accept: 'application/json',
                    'X-Family-Id': this.familyIdValue,
                },
                body: body ? JSON.stringify(body) : undefined,
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
